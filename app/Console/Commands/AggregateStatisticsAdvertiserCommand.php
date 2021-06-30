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
use Adshares\Adserver\Exceptions\Advertiser\MissingEventsException;
use Adshares\Adserver\Models\EventLogsHourlyMeta;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class AggregateStatisticsAdvertiserCommand extends BaseCommand
{
    public const COMMAND_SIGNATURE = 'ops:stats:aggregate:advertiser';

    protected $signature = self::COMMAND_SIGNATURE . ' {--hour=} {--B|bulk}';

    protected $description = 'Aggregates events data for statistics';

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
            $this->info('Command ' . self::COMMAND_SIGNATURE . ' already running');

            return;
        }

        $this->info('Start command ' . self::COMMAND_SIGNATURE);

        if ($hour !== null) {
            if (false === ($timestamp = strtotime($hour))) {
                $this->error(sprintf('[Aggregate statistics] Invalid hour option format "%s"', $hour));
                $this->release();

                return;
            }

            $this->invalidateSelectedHours($timestamp, (bool)$this->option('bulk'));
        }

        $this->aggregateAllInvalidHours();

        $this->info('End command ' . self::COMMAND_SIGNATURE);
        $this->release();
    }

    private function invalidateSelectedHours(int $timestamp, bool $isBulk): void
    {
        $fromHour = DateUtils::roundTimestampToHour($timestamp);
        $currentHour = DateUtils::roundTimestampToHour(time());

        while ($currentHour > $fromHour) {
            EventLogsHourlyMeta::invalidate($fromHour);

            if (!$isBulk) {
                break;
            }

            $fromHour += DateUtils::HOUR;
        }
    }

    private function aggregateAllInvalidHours(): void
    {
        $collection = EventLogsHourlyMeta::fetchInvalid();
        /** @var EventLogsHourlyMeta $logsHourlyMeta */
        foreach ($collection as $logsHourlyMeta) {
            $startTime = microtime(true);
            DB::beginTransaction();

            try {
                $this->aggregateForHour(new DateTime('@' . $logsHourlyMeta->id));

                if ($logsHourlyMeta->isActual()) {
                    $logsHourlyMeta->updateAfterProcessing(
                        EventLogsHourlyMeta::STATUS_VALID,
                        (int)((microtime(true) - $startTime) * 1000)
                    );

                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (MissingEventsException $missingEventsException) {
                DB::rollBack();

                $logsHourlyMeta->updateAfterProcessing(
                    EventLogsHourlyMeta::STATUS_ERROR,
                    (int)((microtime(true) - $startTime) * 1000)
                );

                $this->error($missingEventsException->getMessage());
            } catch (Throwable $throwable) {
                DB::rollBack();
                $this->error(
                    sprintf(
                        'Error during aggregating publisher statistics for timestamp=%d (%s)',
                        $logsHourlyMeta->id,
                        $throwable->getMessage()
                    )
                );
            }
        }
    }

    private function aggregateForHour(DateTime $from): void
    {
        $to = (clone $from)->setTime((int)$from->format('H'), 59, 59, 999999);

        $this->info(
            sprintf(
                '[Aggregate statistics] Processes events from %s to %s',
                $from->format(DateTimeInterface::ATOM),
                $to->format(DateTimeInterface::ATOM)
            )
        );

        $this->statsRepository->aggregateStatistics($from, $to);
    }
}
