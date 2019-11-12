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

use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Utilities\DateUtils;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class AggregateCaseStatisticsPublisherCommand extends BaseCommand
{
    protected $signature = 'ops:stats:aggregate:publisher {--hour=} {--B|bulk}';

    protected $description = 'Aggregates network cases data for statistics';

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

            $this->invalidateSelectedHours($timestamp, (bool)$this->option('bulk'));
        }

        $this->aggregateAllInvalidHours();

        $this->info('End command '.$this->getName());
    }

    private function invalidateSelectedHours(int $timestamp, bool $isBulk): void
    {
        $fromHour = DateUtils::roundTimestampToHour($timestamp);
        $currentHour = DateUtils::roundTimestampToHour(time());

        while ($currentHour > $fromHour) {
            NetworkCaseLogsHourlyMeta::invalidate($fromHour);

            if (!$isBulk) {
                break;
            }

            $fromHour += DateUtils::HOUR;
        }
    }

    private function aggregateAllInvalidHours(): void
    {
        $collection = NetworkCaseLogsHourlyMeta::fetchInvalid();
        /** @var NetworkCaseLogsHourlyMeta $logsHourlyMeta */
        foreach ($collection as $logsHourlyMeta) {
            $startTime = microtime(true);
            DB::beginTransaction();

            try {
                $this->aggregateForHour(new DateTimeImmutable('@'.$logsHourlyMeta->id));

                if ($logsHourlyMeta->isActual()) {
                    $logsHourlyMeta->status = NetworkCaseLogsHourlyMeta::STATUS_VALID;
                    $logsHourlyMeta->process_time_last = (int)((microtime(true) - $startTime) * 1000);
                    $logsHourlyMeta->process_count++;
                    $logsHourlyMeta->save();

                    DB::commit();
                } else {
                    DB::rollBack();
                }
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

    private function aggregateForHour(DateTimeImmutable $from): void
    {
        $to = $from->setTime((int)$from->format('H'), 59, 59, 999999);

        $this->info(
            sprintf(
                '[Aggregate statistics] Processes network events from %s to %s',
                $from->format(DateTime::ATOM),
                $to->format(DateTime::ATOM)
            )
        );

        $queries = $this->prepareQueries($from, $to);

        DB::beginTransaction();
        try {
            foreach ($queries as $query) {
                DB::insert($query);
            }
        } catch (Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        }
        DB::commit();
    }

    private function prepareQueries(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $queries = [];

        $search = ['#date_start', '#date_end'];
        $replace = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        foreach ([
            self::SQL_TEMPLATE_UPDATE_AGGREGATES_WITH_CASES,
            self::SQL_TEMPLATE_UPDATE_AGGREGATES_WITH_PAYMENTS,
        ] as $queryTemplate) {
            $queries[] = str_replace(
                $search,
                $replace,
                $queryTemplate
            );
        }

        return $queries;
    }

    private const SQL_TEMPLATE_UPDATE_AGGREGATES_WITH_CASES = <<<SQL
INSERT INTO network_case_logs_hourly (publisher_id, site_id, zone_id, domain, hour_timestamp,
                                      views_all, views, views_unique, clicks_all, clicks, revenue_case)
SELECT
  publisher_id,
  site_id,
  zone_id,
  domain,
  '#date_start'                                             AS hour_timestamp,
  COUNT(1)                                                  AS views_all,
  SUM(IF(amount IS NOT NULL, 1, 0))                         AS views,
  COUNT(DISTINCT (IF(amount IS NOT NULL, uid, NULL)))       AS views_unique,
  SUM(IF(is_view_clicked = 1, 1, 0))                        AS clicks_all,
  SUM(IF(is_view_clicked = 1 AND amount IS NOT NULL, 1, 0)) AS clicks,
  SUM(IF(amount IS NOT NULL, amount, 0))                    AS revenue_case
FROM
  (SELECT
     c.publisher_id                                                                             AS publisher_id,
     c.site_id                                                                                  AS site_id,
     c.zone_id                                                                                  AS zone_id,
     c.domain                                                                                   AS domain,
     IFNULL(i.user_id, i.tracking_id)                                                           AS uid,
     IF(clicks.network_case_id IS NULL, 0, 1)                                                   AS is_view_clicked,
     (SELECT SUM(paid_amount_currency) FROM network_case_payments WHERE network_case_id = c.id) AS amount
   FROM network_cases c
         LEFT JOIN network_case_clicks clicks ON c.id=clicks.network_case_id
         JOIN network_impressions i ON c.network_impression_id = i.id
   WHERE c.created_at BETWEEN '#date_start' AND '#date_end') d
GROUP BY 1, 2, 3, 4
ON DUPLICATE KEY UPDATE
  views_all=VALUES(views_all),
  views=VALUES(views),
  views_unique=VALUES(views_unique),
  clicks_all=VALUES(clicks_all),
  clicks=VALUES(clicks),
  revenue_case=VALUES(revenue_case);
SQL;

    private const SQL_TEMPLATE_UPDATE_AGGREGATES_WITH_PAYMENTS = <<<SQL
INSERT INTO network_case_logs_hourly (publisher_id, site_id, zone_id, domain, hour_timestamp, revenue_hour)
SELECT
  c.publisher_id              AS publisher_id,
  c.site_id                   AS site_id,
  c.zone_id                   AS zone_id,
  c.domain                    AS domain,
  '#date_start'               AS hour_timestamp,
  SUM(p.paid_amount_currency) AS revenue_hour
FROM network_case_payments p
       JOIN network_cases c ON p.network_case_id = c.id
WHERE pay_time BETWEEN '#date_start' AND '#date_end'
GROUP BY 1, 2, 3, 4
ON DUPLICATE KEY UPDATE revenue_hour=VALUES(revenue_hour);
SQL;
}
