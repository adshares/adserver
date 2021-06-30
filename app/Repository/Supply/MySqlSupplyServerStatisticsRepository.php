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

namespace Adshares\Adserver\Repository\Supply;

use Adshares\Adserver\Facades\DB;
use Adshares\Common\Exception\RuntimeException;

class MySqlSupplyServerStatisticsRepository
{
    private const QUERY_STATISTICS = <<<SQL
SELECT
  DATE_FORMAT(e.hour_timestamp, "%Y-%m-%d")                       AS date,
  SUM(views)                                                      AS impressions,
  SUM(clicks)                                                     AS clicks,
  ROUND((SUM(e.revenue_case) / 100000000000) / #volume_coefficient, 2) AS volume
FROM network_case_logs_hourly e
WHERE e.hour_timestamp < DATE(NOW())
  AND e.hour_timestamp >= DATE(NOW()) - INTERVAL 30 DAY
GROUP BY 1;
SQL;

    private const QUERY_DOMAINS = <<<SQL
SELECT
  s.domain                                                          AS name,
  SUM(l.views)                                                      AS impressions,
  SUM(l.clicks)                                                     AS clicks,
  ROUND((SUM(l.revenue_case) / 100000000000) / #volume_coefficient, 2) AS volume
FROM network_case_logs_hourly l JOIN sites s ON l.site_id = s.uuid
WHERE l.hour_timestamp < DATE(NOW()) - INTERVAL #offset DAY
  AND l.hour_timestamp >= DATE(NOW()) - INTERVAL #offset+#days DAY
GROUP BY 1
HAVING impressions > 0;
SQL;

    private const QUERY_SIZES = <<<SQL
SELECT
  z.size                         AS size,
  IFNULL(SUM(e.views), 0)        AS impressions,
  COUNT(*)                       AS number
FROM zones z
       JOIN sites s ON s.id = z.site_id AND s.status = 2 AND s.deleted_at IS NULL
       LEFT JOIN (
    SELECT e.zone_id, SUM(e.views) AS views
    FROM network_case_logs_hourly e
    WHERE e.hour_timestamp < DATE(NOW())
      AND e.hour_timestamp >= DATE(NOW()) - INTERVAL 30 DAY
    GROUP BY 1
  ) e ON e.zone_id = z.uuid
WHERE z.status = 1
  AND z.deleted_at IS NULL
GROUP BY 1;
SQL;

    public function fetchStatistics(float $totalFee): array
    {
        if ($totalFee >= 1) {
            throw new RuntimeException('Fee coefficient is greater or equal 1.');
        }

        $volumeCoefficient = 1 - $totalFee;

        $query = str_replace('#volume_coefficient', $volumeCoefficient, self::QUERY_STATISTICS);

        return DB::select($query);
    }

    public function fetchZonesSizes(): array
    {
        return DB::select(self::QUERY_SIZES);
    }

    public function fetchDomains(float $totalFee, int $days, int $offset): array
    {
        if ($totalFee >= 1) {
            throw new RuntimeException('Fee coefficient is greater or equal 1.');
        }

        $volumeCoefficient = 1 - $totalFee;

        $query = str_replace(
            [
                '#volume_coefficient',
                '#days',
                '#offset',
            ],
            [
                $volumeCoefficient,
                $days,
                $offset
            ],
            self::QUERY_DOMAINS
        );

        return DB::select($query);
    }
}
