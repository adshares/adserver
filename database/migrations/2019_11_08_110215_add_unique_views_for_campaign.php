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
        } else {
            $this->makeColumnsNullable();
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
        } else {
            $this->revertMakeColumnsNullable();
        }
    }

    private function makeColumnsNullable(): void
    {
        DB::statement('ALTER TABLE event_logs_hourly RENAME TO __event_logs_hourly;');
        Schema::create(
            'event_logs_hourly',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('hour_timestamp')->nullable(false);

                $table->binary('advertiser_id')->nullable(false);
                $table->binary('campaign_id')->nullable(false);
                $table->binary('banner_id')->nullable();
                $table->string('domain', 255)->nullable();

                $table->bigInteger('cost')->nullable(false);
                $table->unsignedInteger('clicks')->nullable(false);
                $table->unsignedInteger('views')->nullable(false);
                $table->unsignedInteger('clicks_all')->nullable(false);
                $table->unsignedInteger('views_all')->nullable(false);
                $table->unsignedInteger('views_unique')->nullable(false);
            }
        );
        DB::statement(
            'INSERT INTO event_logs_hourly('
            .'id, hour_timestamp, advertiser_id, campaign_id, banner_id, domain, cost, clicks, views, '
            .'clicks_all, views_all, views_unique) '
            .'SELECT id, hour_timestamp, advertiser_id, campaign_id, banner_id, domain, cost, clicks, views, '
            .'clicks_all, views_all, views_unique '
            .'FROM __event_logs_hourly;'
        );
        DB::statement('DROP TABLE __event_logs_hourly;');

        DB::statement('ALTER TABLE network_case_logs_hourly RENAME TO __network_case_logs_hourly;');
        Schema::create(
            'network_case_logs_hourly',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('hour_timestamp')->useCurrent();
                $table->binary('publisher_id');
                $table->binary('site_id');
                $table->binary('zone_id')->nullable();
                $table->string('domain', 255)->nullable();

                $table->bigInteger('revenue_case')->default(0);
                $table->bigInteger('revenue_hour')->default(0);
                $table->unsignedInteger('views_all')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('views_unique')->default(0);
                $table->unsignedInteger('clicks_all')->default(0);
                $table->unsignedInteger('clicks')->default(0);
            }
        );
        DB::statement(
            'INSERT INTO network_case_logs_hourly('
            .'id, hour_timestamp, publisher_id, site_id, zone_id, domain, revenue_case, revenue_hour, '
            .'views_all, views, views_unique, clicks_all, clicks) '
            .'SELECT id, hour_timestamp, publisher_id, site_id, zone_id, domain, revenue_case, revenue_hour, '
            .'views_all, views, views_unique, clicks_all, clicks '
            .'FROM __network_case_logs_hourly;'
        );
        DB::statement('DROP TABLE __network_case_logs_hourly;');
    }

    private function revertMakeColumnsNullable(): void
    {
        DB::statement('ALTER TABLE network_case_logs_hourly RENAME TO __network_case_logs_hourly;');
        Schema::create(
            'network_case_logs_hourly',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('hour_timestamp')->useCurrent();
                $table->binary('publisher_id');
                $table->binary('site_id');
                $table->binary('zone_id');
                $table->string('domain', 255);

                $table->bigInteger('revenue_case')->default(0);
                $table->bigInteger('revenue_hour')->default(0);
                $table->unsignedInteger('views_all')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('views_unique')->default(0);
                $table->unsignedInteger('clicks_all')->default(0);
                $table->unsignedInteger('clicks')->default(0);
            }
        );
        DB::statement(
            'INSERT INTO network_case_logs_hourly('
            .'id, hour_timestamp, publisher_id, site_id, zone_id, domain, revenue_case, revenue_hour, '
            .'views_all, views, views_unique, clicks_all, clicks) '
            .'SELECT id, hour_timestamp, publisher_id, site_id, zone_id, domain, revenue_case, revenue_hour, '
            .'views_all, views, views_unique, clicks_all, clicks '
            .'FROM __network_case_logs_hourly;'
        );
        DB::statement('DROP TABLE __network_case_logs_hourly;');

        DB::statement('ALTER TABLE event_logs_hourly RENAME TO __event_logs_hourly;');
        Schema::create(
            'event_logs_hourly',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('hour_timestamp')->nullable(false);

                $table->binary('advertiser_id')->nullable(false);
                $table->binary('campaign_id')->nullable(false);
                $table->binary('banner_id')->nullable(false);
                $table->string('domain', 255)->nullable(false);

                $table->bigInteger('cost')->nullable(false);
                $table->unsignedInteger('clicks')->nullable(false);
                $table->unsignedInteger('views')->nullable(false);
                $table->unsignedInteger('clicks_all')->nullable(false);
                $table->unsignedInteger('views_all')->nullable(false);
                $table->unsignedInteger('views_unique')->nullable(false);
            }
        );
        DB::statement(
            'INSERT INTO event_logs_hourly('
            .'id, hour_timestamp, advertiser_id, campaign_id, banner_id, domain, cost, clicks, views, '
            .'clicks_all, views_all, views_unique) '
            .'SELECT id, hour_timestamp, advertiser_id, campaign_id, banner_id, domain, cost, clicks, views, '
            .'clicks_all, views_all, views_unique '
            .'FROM __event_logs_hourly;'
        );
        DB::statement('DROP TABLE __event_logs_hourly;');
    }
}
