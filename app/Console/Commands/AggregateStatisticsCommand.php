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

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Advertiser\Repository\StatsRepository as AdvertiserStatsRepository;
use Adshares\Publisher\Repository\StatsRepository as PublisherStatsRepository;
use DateTime;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\LockableTrait;

class AggregateStatisticsCommand extends Command
{
    use LineFormatterTrait;
    use LockableTrait;

    protected $signature = 'ops:stats:aggregate {--A|advertiser} {--P|publisher} {--hour=}';

    protected $description = 'Aggregates events data for statistics';

    /** @var AdvertiserStatsRepository */
    private $advertiserStatsRepository;

    /** @var PublisherStatsRepository */
    private $publisherStatsRepository;

    public function __construct(
        AdvertiserStatsRepository $advertiserStatsRepository,
        PublisherStatsRepository $publisherStatsRepository
    ) {
        $this->advertiserStatsRepository = $advertiserStatsRepository;
        $this->publisherStatsRepository = $publisherStatsRepository;

        parent::__construct();
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('[Aggregate statistics] Command '.$this->signature.' already running.');

            return;
        }

        $this->info('Start command '.$this->signature);

        $hour = $this->option('hour');
        if ($hour !== null) {
            if (false === ($from = DateTime::createFromFormat(DateTime::ATOM, $hour))) {
                $this->error(sprintf('[Aggregate statistics] Invalid hour option format "%s"', $hour));

                return;
            }
        } else {
            $from = DateUtils::getDateTimeRoundedToCurrentHour()->modify('-1 hour');
        }

        $to = (clone $from)->setTime((int)$from->format('H'), 59, 59, 999);

        $this->info(
            sprintf(
                '[Aggregate statistics] Processes events from %s to %s',
                $from->format(DateTime::ATOM),
                $to->format(DateTime::ATOM)
            )
        );

        $processAdvertiserEvents = $this->option('advertiser');
        $processPublisherEvents = $this->option('publisher');

        if (!$processAdvertiserEvents && !$processPublisherEvents) {
            $processAdvertiserEvents = true;
            $processPublisherEvents = true;
        }
        
        if ($processAdvertiserEvents) {
            $this->info('[Aggregate statistics] Processing advertiser events');
            $this->advertiserStatsRepository->aggregateStatistics($from, $to);
        }

        if ($processPublisherEvents) {
            $this->info('[Aggregate statistics] Processing publisher events');
            $this->publisherStatsRepository->aggregateStatistics($from, $to);
        }

        $this->info('End command '.$this->signature);
    }
}
