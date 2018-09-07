<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkEventLogsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_event_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();

            $table->binary('cid', 16); // REQ CUSTOM ALTER
            $table->binary('tid', 16); // REQ CUSTOM ALTER

            $table->binary('banner_id', 16); // REQ CUSTOM ALTER
            $table->string('event_type', 16);

            $table->binary('pay_from', 6); // REQ CUSTOM ALTER

            $table->binary('ip', 8); // REQ CUSTOM ALTER

            $table->text('context')->nullable();

            $table->binary('user_id', 16)->nullable(); // REQ CUSTOM ALTER
            $table->integer('human_score')->nullable();
            $table->text('our_userdata')->nullable();
            $table->text('their_userdata')->nullable();
            $table->decimal('event_value', 20, 9)->nullable();
            $table->decimal('paid_amount', 20, 9)->nullable();
            $table->integer('payment_id')->nullable();
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE network_event_logs MODIFY cid varbinary(16)");
            DB::statement("ALTER TABLE network_event_logs MODIFY tid varbinary(16)");
            DB::statement("ALTER TABLE network_event_logs MODIFY banner_id varbinary(16)");
            DB::statement("ALTER TABLE network_event_logs MODIFY pay_from varbinary(6)");
            DB::statement("ALTER TABLE network_event_logs MODIFY ip varbinary(8)");
            DB::statement("ALTER TABLE network_event_logs MODIFY user_id varbinary(16)");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_event_logs');
    }
}
