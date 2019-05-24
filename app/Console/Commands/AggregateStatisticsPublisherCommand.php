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
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use Illuminate\Console\Command;

class AggregateStatisticsPublisherCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:stats:aggregate:publisher {--hour=}';

    protected $description = 'Aggregates network events data for statistics';

    /** @var StatsRepository */
    private $statsRepository;

    public function __construct(StatsRepository $statsRepository)
    {
        $this->statsRepository = $statsRepository;

        parent::__construct();
    }

    public function handle(): void
    {
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
                '[Aggregate statistics] Processes network events from %s to %s',
                $from->format(DateTime::ATOM),
                $to->format(DateTime::ATOM)
            )
        );

        $this->statsRepository->aggregateStatistics($from, $to);

        $this->info('End command '.$this->signature);
    }
}
