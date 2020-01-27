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

class CreateConversionsHourlyTable extends Migration
{
    private const SQL_INSERT_CONVERSION_AGGREGATES = <<<SQL
INSERT INTO conversions_hourly (hour_timestamp,
                                conversion_definition_id,
                                advertiser_id,
                                campaign_id,
                                cost,
                                occurrences)
SELECT CONCAT(DATE(s.created_at), ' ', LPAD(HOUR(s.created_at), 2, '0'), ':00:00') AS hour_timestamp,
       s.conversion_definition_id                                                  AS conversion_definition_id,
       c.user_id                                                                   AS advertiser_id,
       c.id                                                                        AS campaign_id,
       SUM(s.cost)                                                                 AS cost,
       COUNT(1)                                                                    AS occurrences
FROM (
         SELECT group_id,
                conversion_definition_id,
                MIN(created_at)             AS created_at,
                IFNULL(SUM(event_value_currency), 0) AS cost
         FROM conversions
         WHERE payment_id IS NOT NULL
         GROUP BY 1, 2
     ) s
         JOIN conversion_definitions cd on s.conversion_definition_id = cd.id
         JOIN campaigns c on cd.campaign_id = c.id
GROUP BY 1, 2;
SQL;

    public function up(): void
    {
        Schema::create(
            'conversions_hourly',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('hour_timestamp');
                $table->bigInteger('advertiser_id');
                $table->bigInteger('campaign_id');
                $table->bigInteger('conversion_definition_id');
                $table->bigInteger('cost')->default(0);
                $table->unsignedInteger('occurrences')->default(0);
            }
        );

        Schema::table(
            'conversions_hourly',
            function (Blueprint $table) {
                $table->index(
                    [
                        'hour_timestamp',
                        'advertiser_id',
                        'campaign_id',
                    ],
                    'conversions_hourly_index'
                );
            }
        );

        DB::statement(self::SQL_INSERT_CONVERSION_AGGREGATES);
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions_hourly');
    }
}
