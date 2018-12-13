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

class CreateNetworkPaymentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->binary('receiver_address', 6);
            $table->binary('sender_address', 6);
            $table->string('sender_host', 32);
            $table->bigInteger('amount')->unsigned()->nullable();

            $table->binary('tx_id', 8);
            $table->integer('tx_time');
            $table->boolean('detailed_data_used');
            $table->boolean('processed');
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE network_payments MODIFY receiver_address varbinary(6)");
            DB::statement("ALTER TABLE network_payments MODIFY sender_address varbinary(6)");
            DB::statement("ALTER TABLE network_payments MODIFY tx_id varbinary(8)");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_payments');
    }
}
