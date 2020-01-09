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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStatsAggregatesTables extends Migration
{
    private const SQL_DELETE_FROM_EVENT_LOGS_HOURLY_NULL_BANNER_ID = <<<SQL
DELETE FROM event_logs_hourly WHERE banner_id IS NULL;
SQL;
    private const SQL_DELETE_FROM_NETWORK_CASE_LOGS_HOURLY_NULL_ZONE_ID = <<<SQL
DELETE FROM network_case_logs_hourly WHERE zone_id IS NULL;
SQL;

    private const SQL_INSERT_STATISTICS_AGGREGATES_ADVERTISERS = <<<SQL
INSERT INTO event_logs_hourly_stats (hour_timestamp,
                                     advertiser_id,
                                     campaign_id,
                                     banner_id,
                                     cost,
                                     cost_payment,
                                     clicks,
                                     views,
                                     clicks_all,
                                     views_all,
                                     views_unique)
SELECT hour_timestamp,
       advertiser_id,
       campaign_id,
       banner_id,
       SUM(cost),
       SUM(cost_payment),
       SUM(clicks),
       SUM(views),
       SUM(clicks_all),
       SUM(views_all),
       SUM(views_unique)
FROM event_logs_hourly
GROUP BY 1, 2, 3, 4;
SQL;

    private const SQL_INSERT_STATISTICS_AGGREGATES_PUBLISHERS = <<<SQL
INSERT INTO network_case_logs_hourly_stats (hour_timestamp,
                                            publisher_id,
                                            site_id,
                                            zone_id,
                                            revenue_case,
                                            revenue_hour,
                                            views_all,
                                            views,
                                            views_unique,
                                            clicks_all,
                                            clicks)
SELECT hour_timestamp,
       publisher_id,
       site_id,
       zone_id,
       SUM(revenue_case),
       SUM(revenue_hour),
       SUM(views_all),
       SUM(views),
       SUM(views_unique),
       SUM(clicks_all),
       SUM(clicks)
FROM network_case_logs_hourly
GROUP BY 1, 2, 3, 4;
SQL;

    public function up(): void
    {
        $this->createTableEventLogsHourlyStatsForAdvertisers();
        DB::statement(self::SQL_INSERT_STATISTICS_AGGREGATES_ADVERTISERS);
        DB::statement(self::SQL_DELETE_FROM_EVENT_LOGS_HOURLY_NULL_BANNER_ID);

        $this->createTableEventLogsHourlyStatsForPublishers();
        DB::statement(self::SQL_INSERT_STATISTICS_AGGREGATES_PUBLISHERS);
        DB::statement(self::SQL_DELETE_FROM_NETWORK_CASE_LOGS_HOURLY_NULL_ZONE_ID);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs_hourly_stats');
        Schema::dropIfExists('network_case_logs_hourly_stats');
    }

    private function createTableEventLogsHourlyStatsForAdvertisers(): void
    {
        Schema::create(
            'event_logs_hourly_stats',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('hour_timestamp');

                $table->binary('advertiser_id');
                $table->binary('campaign_id');
                $table->binary('banner_id')->nullable();

                $table->bigInteger('cost');
                $table->bigInteger('cost_payment');
                $table->unsignedInteger('clicks');
                $table->unsignedInteger('views');
                $table->unsignedInteger('clicks_all');
                $table->unsignedInteger('views_all');
                $table->unsignedInteger('views_unique');
            }
        );

        DB::statement('ALTER TABLE event_logs_hourly_stats MODIFY advertiser_id varbinary(16) NOT NULL');
        DB::statement('ALTER TABLE event_logs_hourly_stats MODIFY campaign_id varbinary(16) NOT NULL');
        DB::statement('ALTER TABLE event_logs_hourly_stats MODIFY banner_id varbinary(16)');

        Schema::table(
            'event_logs_hourly_stats',
            function (Blueprint $table) {
                $table->unique(
                    [
                        'hour_timestamp',
                        'advertiser_id',
                        'campaign_id',
                        'banner_id',
                    ],
                    'event_logs_hourly_stats_index'
                );
            }
        );
    }

    private function createTableEventLogsHourlyStatsForPublishers(): void
    {
        Schema::create(
            'network_case_logs_hourly_stats',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('hour_timestamp')->useCurrent();
                $table->binary('publisher_id');
                $table->binary('site_id');
                $table->binary('zone_id')->nullable();

                $table->bigInteger('revenue_case')->default(0);
                $table->bigInteger('revenue_hour')->default(0);
                $table->unsignedInteger('views_all')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('views_unique')->default(0);
                $table->unsignedInteger('clicks_all')->default(0);
                $table->unsignedInteger('clicks')->default(0);
            }
        );

        DB::statement('ALTER TABLE network_case_logs_hourly_stats MODIFY publisher_id varbinary(16) NOT NULL');
        DB::statement('ALTER TABLE network_case_logs_hourly_stats MODIFY site_id varbinary(16) NOT NULL');
        DB::statement('ALTER TABLE network_case_logs_hourly_stats MODIFY zone_id varbinary(16)');

        Schema::table(
            'network_case_logs_hourly_stats',
            function (Blueprint $table) {
                $table->unique(
                    [
                        'hour_timestamp',
                        'publisher_id',
                        'site_id',
                        'zone_id',
                    ],
                    'network_case_logs_hourly_stats_index'
                );
            }
        );
    }
}
