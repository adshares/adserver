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

namespace Adshares\Adserver\Repository\Demand;

use Adshares\Adserver\Facades\DB;

class MySqlDemandServerStatisticsRepository
{
    private const QUERY_STATISTICS = <<<SQL
SELECT
  DATE_FORMAT(e.hour_timestamp, "%Y-%m-%d") AS date,
  SUM(views)                                AS impressions,
  SUM(clicks)                               AS clicks,
  ROUND(SUM(e.cost) / 100000000000, 2)      AS volume
FROM event_logs_hourly e
WHERE e.hour_timestamp < DATE(NOW())
  AND e.hour_timestamp >= DATE(NOW()) - INTERVAL 30 DAY
GROUP BY 1;
SQL;

    private const QUERY_DOMAINS = <<<SQL
SELECT
	e.name,
	SUM(e.impressions) AS impressions,
	ROUND(SUM(e.cost)/100000000000, 2) AS cost,
	ROUND(1000 * (SUM(e.cost)/100000000000)/SUM(e.impressions), 2) AS cpm,
	GROUP_CONCAT(DISTINCT b.creative_size) AS sizes
FROM (
	SELECT
		e.banner_id,
		SUBSTRING_INDEX(e.domain, "www.", -1) AS name,
		SUM(e.views) AS impressions,
		SUM(e.cost) AS cost
	FROM event_logs_hourly e
	WHERE e.hour_timestamp < DATE(NOW()) - INTERVAL #offset DAY
      AND e.hour_timestamp >= DATE(NOW()) - INTERVAL #offset+#days DAY
	  AND e.domain != ''
	  AND e.views > 0
	GROUP BY 1, 2
) e
LEFT JOIN banners b ON b.uuid = e.banner_id
GROUP BY 1;
SQL;

    private const QUERY_CAMPAIGNS = <<<SQL
SELECT
    c.name,
    SUM(IFNULL(e.views, 0)) AS impressions,
    ROUND(SUM(IFNULL(e.cost, 0)), 2) AS cost,
    ROUND(1000 * IFNULL(SUM(e.cost)/SUM(e.views), 0), 2) AS cpm,
    GROUP_CONCAT(c.sizes) AS sizes
FROM (
    SELECT e.campaign_id, SUM(e.views) AS views, SUM(e.cost)/100000000000 AS cost
    FROM event_logs_hourly e
    WHERE e.hour_timestamp < DATE(NOW())
      AND e.hour_timestamp >= DATE(NOW()) - INTERVAL 30 DAY
    GROUP BY 1
) e
JOIN (
    SELECT 
        c.uuid AS campaign_id,
        GROUP_CONCAT(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(
          SUBSTRING_INDEX(SUBSTRING_INDEX(c.landing_url, '/', 3), '://', -1), '/', 1), '?', 1), "www.", -1)) AS name,
        GROUP_CONCAT((
            SELECT GROUP_CONCAT(DISTINCT creative_size)
            FROM banners b
            WHERE b.campaign_id = c.id
        )) AS sizes
    FROM campaigns c
    GROUP BY 1
) c ON c.campaign_id = e.campaign_id
GROUP BY 1;
SQL;

    private const QUERY_BANNERS_SIZES = <<<SQL
SELECT
  creative_size AS size,
  IFNULL(SUM(e.views), 0)                          AS impressions,
  COUNT(*)                                         AS number
FROM banners b
       JOIN campaigns c ON c.id = b.campaign_id AND c.status = 2 AND c.deleted_at IS NULL AND c.time_start <= NOW() AND
                           (c.time_end IS NULL OR c.time_end > NOW())
       LEFT JOIN (
    SELECT e.banner_id, SUM(e.views) AS views
    FROM event_logs_hourly e
    WHERE e.hour_timestamp < DATE(NOW())
      AND e.hour_timestamp >= DATE(NOW()) - INTERVAL 30 DAY
    GROUP BY 1
  ) e ON e.banner_id = b.uuid
WHERE b.status = 2
  AND b.deleted_at IS NULL
GROUP BY 1;
SQL;

    public function fetchStatistics(): array
    {
        return DB::select(self::QUERY_STATISTICS);
    }

    public function fetchDomains(int $days, int $offset): array
    {
        $query = str_replace(
            [
                '#days',
                '#offset',
            ],
            [
                $days,
                $offset
            ],
            self::QUERY_DOMAINS
        );

        return DB::select($query);
    }

    public function fetchCampaigns(): array
    {
        return DB::select(self::QUERY_CAMPAIGNS);
    }

    public function fetchBannersSizes(): array
    {
        return DB::select(self::QUERY_BANNERS_SIZES);
    }
}
