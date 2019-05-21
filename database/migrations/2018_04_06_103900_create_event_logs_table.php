<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

class CreateEventLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'event_logs',
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->timestamps();

                $table->binary('case_id', 16)->nullable(false);
                $table->binary('event_id', 16)->nullable(false);
                $table->binary('user_id', 16)->nullable(false);
                $table->binary('banner_id', 16)->nullable(false);
                $table->binary('publisher_id', 16)->nullable();
                $table->binary('advertiser_id', 16)->nullable(false);
                $table->binary('campaign_id', 16)->nullable(false);
                $table->binary('zone_id', 16)->nullable();
                $table->string('event_type', 16);

                $table->binary('pay_to', 6)->nullable();
                $table->binary('ip', 8);
                $table->json('headers')->nullable();

                $table->text('our_context')->nullable();
                $table->text('their_context')->nullable();

                $table->integer('human_score')->nullable();
                $table->text('our_userdata')->nullable();
                $table->text('their_userdata')->nullable();

                $table->bigInteger('event_value')->unsigned()->nullable();
                $table->bigInteger('licence_fee')->unsigned()->nullable();
                $table->bigInteger('operator_fee')->unsigned()->nullable();
                $table->bigInteger('paid_amount')->unsigned()->nullable();
                $table->integer('payment_id')->nullable()->index();

                $table->tinyInteger('reason')->unsigned()->nullable();
                $table->tinyInteger('is_view_clicked')->unsigned()->default(0);
            }
        );

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE event_logs MODIFY case_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY event_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY user_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY publisher_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY advertiser_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY campaign_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY zone_id varbinary(16)');
            DB::statement('ALTER TABLE event_logs MODIFY pay_to varbinary(6)');
            DB::statement('ALTER TABLE event_logs MODIFY ip varbinary(8)');
            DB::statement('ALTER TABLE event_logs MODIFY banner_id varbinary(16) NOT NULL');
        }

        Schema::table('event_logs', function (Blueprint $table) {
            $table->unique('event_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('event_logs');
    }
}
