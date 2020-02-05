<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use function sprintf;

class TargetingReachFetch extends BaseCommand
{
    protected $signature = 'ops:targeting-reach:fetch';

    protected $description = 'Fetches vectors of targeting reach and rates from remote ad servers';

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
                $networkHostId = $networkHost->id;
                /** @var NetworkVectorsMeta $meta */
                $meta = $networkVectorsMetas->get($networkHostId);

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

            Log::warning(
                sprintf(
                    '[TargetingReachFetch] Exception during deleting network vectors (%s)',
                    $throwable->getMessage()
                )
            );
        }
    }

    private function fetchAndStoreRemote(NetworkHost $networkHost): void
    {
        $networkHostId = $networkHost->id;
        $networkHost = $networkHost->host;
        //TODO fetch from remote host
    }
}
