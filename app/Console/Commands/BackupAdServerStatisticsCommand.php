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

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupAdServerStatisticsCommand extends BaseCommand
{
    protected $signature = 'ops:statistics:backup {--day=}';

    protected $description = "Save AdServer's statistics to file";

    public function handle(): void
    {
        $day = $this->option('day');
        if (null === $day && !$this->lock()) {
            $this->info('Command ' . $this->getName() . ' already running');

            return;
        }

        $this->info('Start command ' . $this->getName());

        if ($day !== null) {
            if (false === ($timestamp = strtotime($day))) {
                $this->error(sprintf('[Aggregate statistics] Invalid hour option format "%s"', $day));

                return;
            }

            $date = new DateTimeImmutable('@' . $timestamp);
        } else {
            $date = new DateTimeImmutable('-1 day');
        }

        $this->exportStatistics($date);

        $this->info('End command ' . $this->getName());
    }

    private function exportStatistics(DateTimeImmutable $date): void
    {
        $file = Storage::disk('public')->path($date->format('Ymd') . '_statistics.csv');
        $from = $date->setTime(0, 0);
        $to = $date->setTime(23, 59, 59, 999999);

        $rows = DB::select(
            BackupAdServerStatisticsCommand::SQL_TEMPLATE_EXPORT_STATISTICS,
            ['date_start' => $from, 'date_end' => $to]
        );

        if (($handle = fopen($file, 'w')) !== false) {
            foreach ($rows as $row) {
                fputcsv($handle, get_object_vars($row));
            }
            fclose($handle);
        }
    }

    private const SQL_TEMPLATE_EXPORT_STATISTICS = <<<SQL
SELECT
    domain,
    size,
    country,
    COUNT(1)                                                  AS views_all,
    COUNT(DISTINCT impression_id)                             AS impressions,
    SUM(IF(amount IS NOT NULL, 1, 0))                         AS views,
    COUNT(DISTINCT (IF(amount IS NOT NULL, uid, NULL)))       AS views_unique,
    SUM(IF(is_view_clicked = 1, 1, 0))                        AS clicks_all,
    SUM(IF(is_view_clicked = 1 AND amount IS NOT NULL, 1, 0)) AS clicks,
    SUM(IF(amount IS NOT NULL, amount, 0))                    AS revenue_case
FROM
    (SELECT
         s.domain                                                                                   AS domain,
         size,
         IFNULL(i.country, 'null')                                                                  AS country,
         IFNULL(i.user_id, i.tracking_id)                                                           AS uid,
         IF(clicks.network_case_id IS NULL, 0, 1)                                                   AS is_view_clicked,
         (SELECT SUM(paid_amount_currency) FROM network_case_payments WHERE network_case_id = c.id) AS amount,
         c.network_impression_id                                                                    AS impression_id
     FROM network_cases c
              LEFT JOIN network_case_clicks clicks ON c.id=clicks.network_case_id
              JOIN network_impressions i ON c.network_impression_id = i.id
              JOIN sites s on c.site_id = s.uuid
              JOIN zones z on c.zone_id = z.uuid
     WHERE c.created_at BETWEEN :date_start AND :date_end) d
GROUP BY 1,2,3;
SQL;
}
