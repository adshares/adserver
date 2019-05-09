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

namespace Adshares\Adserver\Tests\Repository\Publisher;

use Adshares\Adserver\Repository\Publisher\MySqlStatsQueryBuilder;
use Adshares\Publisher\Repository\StatsRepository;
use PHPUnit\Framework\TestCase;

final class MysqlStatsQueryBuilderTest extends TestCase
{
    public function testWhenSiteIdIsAppended(): void
    {
        $class = (new MySqlStatsQueryBuilder(StatsRepository::TYPE_STATS))->appendSiteIdGroupBy();

        $query = $class->build();

        $expect = "SELECT SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1"
            ." AND e.paid_amount_currency IS NOT NULL, 1, 0)) AS clicks,"
            ."SUM(IF(e.event_type = 'view' AND e.paid_amount_currency IS NOT NULL, 1, 0)) AS views,"
            ."IFNULL(AVG(CASE WHEN (e.event_type <> 'view' OR e.paid_amount_currency IS NULL)"
            ." THEN NULL WHEN (e.is_view_clicked = 1) THEN 1 ELSE 0 END), 0) AS ctr,"
            ."IFNULL(ROUND(AVG(IF(e.event_type = 'click' AND e.paid_amount_currency IS NOT NULL,"
            ." e.paid_amount_currency, NULL))), 0) AS rpc,"
            ."IFNULL(ROUND(AVG(IF(e.event_type = 'view' AND e.paid_amount_currency IS NOT NULL,"
            ." e.paid_amount_currency, NULL))), 0)*1000 AS rpm,"
            ."SUM(IF(e.event_type IN ('click', 'view') AND e.paid_amount_currency IS NOT NULL,"
            ." e.paid_amount_currency, 0)) AS revenue,"
            ."e.site_id AS site_id FROM network_event_logs e "
            ."INNER JOIN sites s ON s.uuid = e.site_id WHERE s.deleted_at is null "
            ."GROUP BY e.site_id HAVING clicks>0 OR views>0 OR ctr>0 OR rpc>0 OR rpm>0 OR revenue>0";

        $this->assertEquals($expect, $query);
    }
}
