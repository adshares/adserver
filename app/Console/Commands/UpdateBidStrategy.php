<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Common\Application\Service\AdUser;
use Illuminate\Support\Facades\DB;
use Throwable;
use function array_chunk;
use function sprintf;
use function substr;

class UpdateBidStrategy extends BaseCommand
{
    public const COMMAND_SIGNATURE = 'ops:bid-strategy:update';

    private const DOMAINS_CHUNK_SIZE = 50;

    private const SELECT_DOMAINS = <<<SQL
SELECT DISTINCT `key` FROM network_vectors WHERE `key` LIKE 'site:domain:%';
SQL;

    protected $signature = self::COMMAND_SIGNATURE;

    protected $description = 'Updates bid strategy';

    /** @var AdUser */
    private $adUser;

    public function __construct(Locker $locker, AdUser $adUser)
    {
        $this->adUser = $adUser;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', $this->name));

            return;
        }
        $this->info(sprintf('Start command %s', $this->name));

        $this->updateBidStrategy();

        $this->info('Finish updating bid strategy');
        $this->release();
    }

    private function updateBidStrategy(): void
    {
        $rows = DB::select(self::SELECT_DOMAINS);

        DB::beginTransaction();
        try {
            BidStrategy::deleteAll();

            $rowsChunks = array_chunk($rows, self::DOMAINS_CHUNK_SIZE);
            foreach ($rowsChunks as $rowsChunk) {
                $bidStrategies = [];
                foreach ($rowsChunk as $row) {
                    $key = $row->key;
                    $domain = substr($key, 12);
                    $rank = $this->adUser->fetchDomainRank($domain);

                    $bidStrategies[] = [
                        'category' => $key,
                        'rank' => $rank,
                    ];
                }

                BidStrategy::insert($bidStrategies);
            }

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            $this->error(sprintf('Exception during updating bid strategy (%s)', $throwable->getMessage()));
        }
    }
}
