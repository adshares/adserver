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

use Adshares\Adserver\Exceptions\Publisher\MissingCasesException;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Utilities\DateUtils;
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
            $this->info('Command ' . $this->getName() . ' already running');

            return;
        }

        $this->info('Start command ' . $this->getName());

        if ($hour !== null) {
            if (false === ($timestamp = strtotime($hour))) {
                $this->error(sprintf('[Aggregate statistics] Invalid hour option format "%s"', $hour));

                return;
            }

            $this->invalidateSelectedHours($timestamp, (bool)$this->option('bulk'));
        }

        $this->aggregateAllInvalidHours();

        $this->info('End command ' . $this->getName());
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
                $this->aggregateForHour(new DateTimeImmutable('@' . $logsHourlyMeta->id));

                if ($logsHourlyMeta->isActual()) {
                    $logsHourlyMeta->updateAfterProcessing(
                        NetworkCaseLogsHourlyMeta::STATUS_VALID,
                        (int)((microtime(true) - $startTime) * 1000)
                    );

                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (MissingCasesException $missingCasesException) {
                DB::rollBack();

                $logsHourlyMeta->updateAfterProcessing(
                    NetworkCaseLogsHourlyMeta::STATUS_ERROR,
                    (int)((microtime(true) - $startTime) * 1000)
                );

                $this->error(sprintf($missingCasesException->getMessage()));
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
                $from->format(DateTimeInterface::ATOM),
                $to->format(DateTimeInterface::ATOM)
            )
        );

        if (NetworkCase::noCasesInDateRange($from, $to)) {
            throw new MissingCasesException(
                sprintf(
                    '[Aggregate statistics] There are no cases from %s to %s',
                    $from->format(DateTimeInterface::ATOM),
                    $to->format(DateTimeInterface::ATOM)
                )
            );
        }

        $queries = $this->prepareQueries($from, $to);

        foreach ($queries as $query) {
            DB::insert($query);
        }
    }

    private function prepareQueries(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $queries = [];

        $search = ['#date_start', '#date_end'];
        $replace = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        foreach (
            [
            self::SQL_TEMPLATE_UPDATE_AGGREGATES_WITH_CASES,
            self::SQL_TEMPLATE_UPDATE_AGGREGATES_WITH_PAYMENTS,
            self::SQL_TEMPLATE_DELETE_STATS_AGGREGATES,
            self::SQL_TEMPLATE_INSERT_STATS_AGGREGATES,
            self::SQL_TEMPLATE_INSERT_STATS_AGGREGATES_GROUPED_BY_SITE,
            ] as $queryTemplate
        ) {
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

    private const SQL_TEMPLATE_DELETE_STATS_AGGREGATES = <<<SQL
DELETE FROM network_case_logs_hourly_stats WHERE hour_timestamp = '#date_start';
SQL;

    private const SQL_TEMPLATE_INSERT_STATS_AGGREGATES = <<<SQL
INSERT INTO network_case_logs_hourly_stats (publisher_id,
                                            site_id,
                                            zone_id,
                                            views_all,
                                            views,
                                            views_unique,
                                            clicks_all,
                                            clicks,
                                            revenue_case,
                                            revenue_hour,
                                            hour_timestamp)
SELECT
  publisher_id,
  site_id,
  zone_id,
  SUM(views_all),
  SUM(views),
  SUM(views_unique),
  SUM(clicks_all),
  SUM(clicks),
  SUM(revenue_case),
  SUM(revenue_hour),
  '#date_start'
FROM network_case_logs_hourly
WHERE hour_timestamp = '#date_start'
GROUP BY 1, 2, 3;
SQL;

    private const SQL_TEMPLATE_INSERT_STATS_AGGREGATES_GROUPED_BY_SITE = <<<SQL
INSERT INTO network_case_logs_hourly_stats (publisher_id, site_id, hour_timestamp, views_all, views, views_unique,
                                            clicks_all, clicks, revenue_case, revenue_hour)
SELECT
  publisher_id,
  site_id,
  '#date_start',
  SUM(views_all),
  SUM(views),
  SUM(views_unique),
  SUM(clicks_all),
  SUM(clicks) ,
  SUM(revenue_case),
  SUM(revenue_hour)
FROM
  (SELECT
     c.publisher_id              AS publisher_id,
     c.site_id                   AS site_id,
     0                           AS views_all,
     0                           AS views,
     0                           AS views_unique,
     0                           AS clicks_all,
     0                           AS clicks,
     0                           AS revenue_case,
     SUM(p.paid_amount_currency) AS revenue_hour
   FROM network_case_payments p
          JOIN network_cases c ON p.network_case_id = c.id
   WHERE pay_time BETWEEN '#date_start' AND '#date_end'
   GROUP BY 1, 2

   UNION

   SELECT
     publisher_id,
     site_id,
     COUNT(1)                                                  AS views_all,
     SUM(IF(amount IS NOT NULL, 1, 0))                         AS views,
     COUNT(DISTINCT (IF(amount IS NOT NULL, uid, NULL)))       AS views_unique,
     SUM(IF(is_view_clicked = 1, 1, 0))                        AS clicks_all,
     SUM(IF(is_view_clicked = 1 AND amount IS NOT NULL, 1, 0)) AS clicks,
     SUM(IF(amount IS NOT NULL, amount, 0))                    AS revenue_case,
     0                                                         AS revenue_hour
   FROM
     (SELECT
        c.publisher_id                                                                             AS publisher_id,
        c.site_id                                                                                  AS site_id,
        IFNULL(i.user_id, i.tracking_id)                                                           AS uid,
        IF(clicks.network_case_id IS NULL, 0, 1)                                                   AS is_view_clicked,
        (SELECT SUM(paid_amount_currency) FROM network_case_payments WHERE network_case_id = c.id) AS amount
      FROM network_cases c
             LEFT JOIN network_case_clicks clicks ON c.id = clicks.network_case_id
             JOIN network_impressions i ON c.network_impression_id = i.id
      WHERE c.created_at BETWEEN '#date_start' AND '#date_end') d
   GROUP BY 1, 2) u
GROUP BY 1,2;
SQL;
}
