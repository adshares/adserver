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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Facades\DB;
use Adshares\Supply\Application\Dto\InfoStatistics;

class MySqlServerStatisticsRepository
{
    public function fetchInfoStatistics(): InfoStatistics
    {
        $result = DB::select(
            <<<SQL
 SELECT 
       (SELECT COUNT(*) AS count
        FROM users
        WHERE deleted_at IS NULL
          AND updated_at > NOW() - INTERVAL 3 MONTH
       ) AS users,
       (SELECT COUNT(*) AS count
        FROM campaigns
        WHERE status = 2
          AND deleted_at IS NULL
          AND time_start <= NOW()
          AND (time_end IS NULL OR time_end > NOW())
       ) AS campaigns,
       (SELECT COUNT(*) FROM (
          SELECT SUBSTRING_INDEX(e.domain, "www.", -1) AS count, SUM(e.views) AS views
          FROM event_logs_hourly e
          WHERE e.hour_timestamp < DATE(NOW()) - INTERVAL 0 DAY
            AND e.hour_timestamp >= DATE(NOW()) - INTERVAL 1 DAY
            AND e.domain != '' GROUP BY 1
          ) d WHERE d.views > 100
       ) AS sites;
SQL
        );

        $row = $result[0];

        return new InfoStatistics((int)$row->users, (int)$row->campaigns, (int)$row->sites);
    }
}
