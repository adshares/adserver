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
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;

class AggregateStatisticsPublisherCommand extends BaseCommand
{
    protected $signature = 'legacy:ops:stats:aggregate:publisher {--hour=} {--B|bulk}';

    protected $description = 'Aggregates network events data for statistics';

    /** @var StatsRepository */
    private $statsRepository;

    public function __construct(Locker $locker, StatsRepository $statsRepository)
    {
        $this->statsRepository = $statsRepository;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        $hour = $this->option('hour');
        if (null === $hour && !$this->lock()) {
            $this->info('Command '.$this->getName().' already running');

            return;
        }

        $this->info('Start command '.$this->getName());

        if ($hour !== null) {
            if (false === ($timestamp = strtotime($hour))) {
                $this->error(sprintf('[Aggregate statistics] Invalid hour option format "%s"', $hour));

                return;
            }

            $from = DateUtils::getDateTimeRoundedToCurrentHour((new DateTime())->setTimestamp($timestamp));
        } else {
            $from = DateUtils::getDateTimeRoundedToCurrentHour()->modify('-1 hour');
        }

        $isBulk = $this->option('bulk');
        $currentHour = DateUtils::getDateTimeRoundedToCurrentHour();

        while ($currentHour > $from) {
            $this->aggregateForHour($from);

            if (!$isBulk) {
                break;
            }

            $from = $from->modify('+1 hour');
        }

        $this->info('End command '.$this->getName());
    }

    private function aggregateForHour(DateTime $from): void
    {
        $to = (clone $from)->setTime((int)$from->format('H'), 59, 59, 999);

        $this->info(
            sprintf(
                '[Aggregate statistics] Processes network events from %s to %s',
                $from->format(DateTime::ATOM),
                $to->format(DateTime::ATOM)
            )
        );

        $this->statsRepository->aggregateStatistics($from, $to);
    }
}
