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

namespace Adshares\Adserver\Tests\Repository\Advertiser;

use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Adserver\Repository\Advertiser\MySqlStatsQueryBuilder;
use PHPUnit\Framework\TestCase;
use function str_replace;

final class MysqlStatsQueryBuilderTest extends TestCase
{
    public function testWhenCampaignIdIsAppended(): void
    {
        $class = (new MySqlStatsQueryBuilder(StatsRepository::STATS_SUM_TYPE))->appendCampaignIdGroupBy();

        $query = $class->build();

        $expect = <<<SQL
SELECT SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1, 1, 0)) AS clicks,
SUM(IF(e.event_type = 'view', 1, 0)) AS views,
IFNULL(AVG(CASE WHEN (e.event_type <> 'view') THEN NULL WHEN (e.is_view_clicked = 1) THEN 1 ELSE 0 END), 0) AS ctr,
IFNULL(ROUND(AVG(IF(e.event_type = 'click', e.event_value_currency, NULL))), 0) AS cpc,
IFNULL(ROUND(AVG(IF(e.event_type = 'view', e.event_value_currency, NULL))), 0)*1000 AS cpm,
SUM(IF(e.event_type IN ('click', 'view'), e.event_value_currency, 0))
 AS cost,e.campaign_id AS campaign_id FROM event_logs e 
INNER JOIN campaigns c ON c.uuid = e.campaign_id WHERE c.deleted_at is null 
GROUP BY e.campaign_id HAVING clicks>0 OR views>0 OR ctr>0 OR cpc>0 OR cpm>0 OR cost>0
SQL;

        $this->assertEquals(str_replace(PHP_EOL, '', $expect), $query);
    }
}
