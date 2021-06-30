<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\SupplyClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

use function array_chunk;
use function sprintf;

class TargetingReachFetch extends BaseCommand
{
    private const CATEGORIES_CHUNK_SIZE = 200;

    protected $signature = 'ops:targeting-reach:fetch';

    protected $description = 'Fetches vectors of targeting reach and rates from remote ad servers';

    /** @var SupplyClient */
    private $client;

    public function __construct(Locker $locker, SupplyClient $client)
    {
        $this->client = $client;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', $this->name));

            return;
        }
        $this->info(sprintf('Start command %s', $this->name));

        $adserverAddress = (string)config('app.adshares_address');
        $dateThreshold = new DateTimeImmutable('-23 hours');
        $networkHosts = NetworkHost::fetchHosts();
        $networkVectorsMetas = NetworkVectorsMeta::fetch()->keyBy('network_host_id');

        $networkHostIdsToDelete =
            array_diff($networkVectorsMetas->pluck('network_host_id')->all(), $networkHosts->pluck('id')->all());
        $this->deleteNetworkVectorsFromUnreachableServers($networkHostIdsToDelete);

        /** @var NetworkHost $networkHost */
        foreach ($networkHosts as $networkHost) {
            if ($adserverAddress !== $networkHost->address) {
                /** @var NetworkVectorsMeta|null $meta */
                $meta = $networkVectorsMetas->get($networkHost->id);

                if (null === $meta || $meta->updated_at < $dateThreshold) {
                    $this->fetchAndStoreRemote($networkHost);
                }
            }
        }

        $this->info('Finish fetching targeting reach');
    }

    private function deleteNetworkVectorsFromUnreachableServers(array $networkHostIdsToDelete): void
    {
        if (!$networkHostIdsToDelete) {
            return;
        }

        try {
            DB::beginTransaction();

            NetworkVectorsMeta::deleteByNetworkHostIds($networkHostIdsToDelete);
            DB::table('network_vectors')->whereIn('network_host_id', $networkHostIdsToDelete)->delete();

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            $this->warn(
                sprintf(
                    '[TargetingReachFetch] Exception during deleting network vectors (%s)',
                    $throwable->getMessage()
                )
            );
        }
    }

    private function fetchAndStoreRemote(NetworkHost $networkHost): void
    {
        try {
            $targetingReach = $this->client->fetchTargetingReach($networkHost->host);
        } catch (RuntimeException | EmptyInventoryException | UnexpectedClientResponseException $exception) {
            $this->warn(
                sprintf(
                    '[TargetingReachFetch] Exception during fetching from %s (%s)',
                    $networkHost->host,
                    $exception->getMessage()
                )
            );

            return;
        }

        $networkHostId = $networkHost->id;
        $totalEventsCount = $targetingReach['meta']['total_events_count'];

        DB::beginTransaction();
        try {
            DB::table('network_vectors')->where('network_host_id', $networkHostId)->delete();

            $categoriesChunks = array_chunk($targetingReach['categories'], self::CATEGORIES_CHUNK_SIZE);
            foreach ($categoriesChunks as $categoriesChunk) {
                $values = [];
                foreach ($categoriesChunk as $data) {
                    $values[] = [
                        'network_host_id' => $networkHostId,
                        'key' => $data['key'],
                        'data' => Utils::urlSafeBase64Decode($data['data']),
                        'occurrences' => $data['occurrences'],
                        'cpm_25' => $data['cpm_25'],
                        'cpm_50' => $data['cpm_50'],
                        'cpm_75' => $data['cpm_75'],
                        'negation_cpm_25' => $data['negation_cpm_25'],
                        'negation_cpm_50' => $data['negation_cpm_50'],
                        'negation_cpm_75' => $data['negation_cpm_75'],
                    ];
                }
                DB::table('network_vectors')->insert($values);
            }

            NetworkVectorsMeta::upsert($networkHostId, $totalEventsCount);
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            $this->warn(
                sprintf('Exception during storing data from %s (%s)', $networkHost->host, $throwable->getMessage())
            );
        }
    }
}
