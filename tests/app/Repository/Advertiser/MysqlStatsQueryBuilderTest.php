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

use Adshares\Adserver\Repository\Advertiser\MySqlStatsQueryBuilder;
use Adshares\Advertiser\Repository\StatsRepository;
use PHPUnit\Framework\TestCase;

final class MysqlStatsQueryBuilderTest extends TestCase
{
    public function testWhenCampaignIdIsAppended(): void
    {
        $class = (new MySqlStatsQueryBuilder(StatsRepository::TYPE_STATS))->appendCampaignIdGroupBy();

        $query = $class->build();

        $expect = "SELECT SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1"
            ." AND e.event_value_currency IS NOT NULL AND e.reason = 0, 1, 0)) AS clicks,"
            ."SUM(IF(e.event_type = 'view' AND e.event_value_currency IS NOT NULL AND e.reason = 0, 1, 0)) AS views,"
            ."SUM(IF(e.event_type IN ('click', 'view') AND e.event_value_currency IS NOT NULL AND e.reason = 0,"
            ." e.event_value_currency, 0)) AS cost,"
            ."e.campaign_id AS campaign_id FROM event_logs e "
            ."INNER JOIN campaigns c ON c.uuid = e.campaign_id WHERE c.deleted_at is null "
            ."GROUP BY e.campaign_id HAVING clicks>0 OR views>0 OR cost>0";

        $this->assertEquals($expect, $query);
    }
}
