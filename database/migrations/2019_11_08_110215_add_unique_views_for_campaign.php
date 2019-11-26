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

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueViewsForCampaign extends Migration
{
    private const SQL_INSERT_ADVERTISER_AGGREGATES_NOT_GROUPED_BY_BANNER_ID =<<<SQL
INSERT INTO event_logs_hourly (hour_timestamp,
                               advertiser_id,
                               campaign_id,
                               cost,
                               clicks,
                               views,
                               clicks_all,
                               views_all,
                               views_unique)
SELECT hour_timestamp,
       advertiser_id,
       campaign_id,
       SUM(cost),
       SUM(clicks),
       SUM(views),
       SUM(clicks_all),
       SUM(views_all),
       0.85 * SUM(views_unique)
FROM event_logs_hourly
GROUP BY 1, 2, 3;
SQL;
    private const SQL_INSERT_PUBLISHER_AGGREGATES_NOT_GROUPED_BY_ZONE_ID =<<<SQL
INSERT INTO network_case_logs_hourly (hour_timestamp,
                                      publisher_id,
                                      site_id,
                                      revenue_case,
                                      revenue_hour,
                                      clicks,
                                      views,
                                      clicks_all,
                                      views_all,
                                      views_unique)
SELECT hour_timestamp,
       publisher_id,
       site_id,
       SUM(revenue_case),
       SUM(revenue_hour),
       SUM(clicks),
       SUM(views),
       SUM(clicks_all),
       SUM(views_all),
       0.85*SUM(views_unique)
FROM network_case_logs_hourly
GROUP BY 1, 2, 3;
SQL;

    public function up(): void
    {
        if (DB::isMySql()) {
            DB::statement('ALTER TABLE event_logs_hourly MODIFY domain VARCHAR(255) NULL;');
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY domain VARCHAR(255) NULL;');
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY zone_id VARBINARY(16) NULL;');
        }
        
        DB::statement(self::SQL_INSERT_ADVERTISER_AGGREGATES_NOT_GROUPED_BY_BANNER_ID);
        DB::statement(self::SQL_INSERT_PUBLISHER_AGGREGATES_NOT_GROUPED_BY_ZONE_ID);
    }

    public function down(): void
    {
        DB::delete('DELETE FROM network_case_logs_hourly WHERE zone_id IS NULL');
        DB::delete('DELETE FROM event_logs_hourly WHERE banner_id IS NULL');

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY zone_id VARBINARY(16) NOT NULL;');
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY domain VARCHAR(255) NOT NULL;');
            DB::statement('ALTER TABLE event_logs_hourly MODIFY domain VARCHAR(255) NOT NULL;');
        }
    }
}
