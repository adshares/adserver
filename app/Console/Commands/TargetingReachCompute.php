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

use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Advertiser\NetworkVectorComputer;
use DateInterval;
use DateTimeImmutable;
use Exception;

use function sprintf;

class TargetingReachCompute extends BaseCommand
{
    protected $signature = 'ops:targeting-reach:compute {--b|before=-2 hours} {--p|period=P1D}';

    protected $description = 'Computes vectors of targeting reach and rates';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', $this->name));

            return;
        }
        $this->info(sprintf('Start command %s', $this->name));

        $before = $this->option('before');
        $period = $this->option('period');

        try {
            $dateTo = new DateTimeImmutable($before);
            $dateFrom = $dateTo->sub(new DateInterval($period));
        } catch (Exception $e) {
            $this->error('[TargetingReachCompute] ' . $e->getMessage());

            return;
        }

        $this->info(
            sprintf(
                'From %s, to %s',
                $dateFrom->format(DateTimeImmutable::ATOM),
                $dateTo->format(DateTimeImmutable::ATOM)
            )
        );

        if (null === ($adServerId = $this->fetchAdserverId())) {
            $this->error('[TargetingReachCompute] Cannot find adserver Id');

            return;
        }

        $startTime = microtime(true);

        (new NetworkVectorComputer($adServerId))->compute($dateFrom, $dateTo);

        $this->info(sprintf('Computing time: %.3f seconds', microtime(true) - $startTime));

        $this->info('Finish computing targeting reach');
    }

    private function fetchAdserverId(): ?int
    {
        if (null === ($networkHost = NetworkHost::fetchByAddress((string)config('app.adshares_address')))) {
            return null;
        }

        return $networkHost->id;
    }
}
