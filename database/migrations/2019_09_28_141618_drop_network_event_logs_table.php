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
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropNetworkEventLogsTable extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('network_event_logs');
        Schema::dropIfExists('network_event_logs_hourly');
    }

    public function down(): void
    {
        Schema::create(
            'network_event_logs',
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->timestamps();

                $table->binary('case_id')->nullable(false);
                $table->binary('event_id')->nullable(false);
                $table->binary('tracking_id')->nullable(false);
                $table->binary('banner_id')->nullable(false);
                $table->binary('publisher_id')->nullable(false);
                $table->binary('site_id')->nullable(false);
                $table->binary('zone_id')->nullable();
                $table->binary('campaign_id')->nullable(false);
                $table->string('event_type', 16);

                $table->binary('pay_from', 6);
                $table->text('context')->nullable();

                $table->decimal('human_score', 3, 2)->nullable();
                $table->text('our_userdata')->nullable();
                $table->text('their_userdata')->nullable();

                $table->bigInteger('event_value')->nullable();
                $table->bigInteger('paid_amount')->nullable();
                $table->decimal('exchange_rate', 9, 5)->nullable();
                $table->bigInteger('paid_amount_currency')->unsigned()->nullable();
                $table->bigInteger('license_fee')->nullable();
                $table->bigInteger('operator_fee')->nullable();
                $table->bigInteger('ads_payment_id')->nullable()->index();

                $table->tinyInteger('is_view_clicked')->unsigned()->default(0);
                $table->string('domain', 255)->nullable();
                $table->binary('user_id')->nullable();
            }
        );

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE network_event_logs MODIFY case_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY event_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY tracking_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY banner_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY publisher_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY site_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY zone_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY campaign_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs MODIFY pay_from varbinary(6)');
            DB::statement('ALTER TABLE network_event_logs MODIFY user_id varbinary(16)');
        }

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->unique('event_id');
                $table->index('created_at', 'network_event_logs_created_at_index');
                $table->index('updated_at', 'network_event_logs_updated_at_index');
            }
        );

        Schema::create(
            'network_event_logs_hourly',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('hour_timestamp')->nullable(false);

                $table->binary('publisher_id')->nullable(false);
                $table->binary('site_id')->nullable(false);
                $table->binary('zone_id')->nullable(false);
                $table->string('domain', 255)->nullable(false);

                $table->bigInteger('revenue')->nullable(false);
                $table->unsignedInteger('clicks')->nullable(false);
                $table->unsignedInteger('views')->nullable(false);
                $table->unsignedInteger('clicks_all')->nullable(false);
                $table->unsignedInteger('views_all')->nullable(false);
                $table->unsignedInteger('views_unique')->nullable(false);
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE network_event_logs_hourly MODIFY publisher_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs_hourly MODIFY site_id varbinary(16)');
            DB::statement('ALTER TABLE network_event_logs_hourly MODIFY zone_id varbinary(16)');
        }

        Schema::table(
            'network_event_logs_hourly',
            function (Blueprint $table) {
                $table->index('hour_timestamp');
                $table->index(['publisher_id', 'site_id']);
            }
        );
    }
}
