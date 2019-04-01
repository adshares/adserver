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
        $class = (new MySqlStatsQueryBuilder(StatsRepository::STATS_SUM_TYPE))->appendSiteIdGroupBy();

        $query = $class->build();

        $expect = <<<SQL
SELECT 
SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1, 1, 0)) AS clicks,
SUM(IF(e.event_type = 'view', 1, 0)) AS views,
IFNULL(AVG(CASE WHEN (e.event_type <> 'view') THEN NULL WHEN (e.is_view_clicked = 1) THEN 1 ELSE 0 END), 0) AS ctr,
IFNULL(AVG(IF(e.event_type = 'click', e.paid_amount, NULL)), 0) AS rpc,
IFNULL(AVG(IF(e.event_type = 'view', e.paid_amount, NULL)), 0)*1000 AS rpm,
SUM(IF(e.event_type IN ('click', 'view'), e.paid_amount, 0)) AS revenue,
e.site_id AS site_id 
FROM network_event_logs e  
GROUP BY e.site_id 
HAVING clicks>0 OR views>0 OR ctr>0 OR rpc>0 OR rpm>0 OR revenue>0
SQL;

        $this->assertEquals($expect, $query);
    }
}
