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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Facades\DB;
use Adshares\Supply\Application\Dto\InfoStatistics;

class MySqlServerStatisticsRepository
{
    public function fetchInfoStatistics(): InfoStatistics
    {
        $result = DB::select(
            <<<SQL
SELECT (SELECT COUNT(*) AS count FROM users WHERE deleted_at IS NULL)                AS users,
       (SELECT COUNT(*) AS count
        FROM campaigns
        WHERE status = 2
          AND deleted_at IS NULL
          AND time_start <= NOW()
          AND (time_end IS NULL OR time_end > NOW()))                                AS campaigns,
       (SELECT COUNT(*) AS count FROM sites WHERE status = 2 AND deleted_at IS NULL) AS sites
SQL
        );

        $row = $result[0];

        return new InfoStatistics((int)$row->users, (int)$row->campaigns, (int)$row->sites);
    }
}
