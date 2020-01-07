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
    private const SQL_INSERT_ADVERTISER_STATISTICS_AGGREGATES = <<<SQL
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

    public function up(): void
    {
        Schema::create(
            'event_logs_hourly_stats',
            function (Blueprint $table) {
                $table->increments('id');
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
                $table->index('hour_timestamp');
                $table->index(['advertiser_id', 'campaign_id']);
            }
        );

        DB::statement(self::SQL_INSERT_ADVERTISER_STATISTICS_AGGREGATES);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs_hourly_stats');
    }
}
