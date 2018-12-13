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

class CreatePaymentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->text('transfers')->nullable();
            $table->text('subthreshold_transfers')->nullable();
            $table->binary('account_address', 6)->nullable();
            $table->binary('account_hashin', 32)->nullable();
            $table->binary('account_hashout', 32)->nullable();
            $table->integer('account_msid')->nullable();
            $table->text('tx_data')->nullable();
            $table->binary('tx_id', 8);
            $table->integer('tx_time');
            $table->bigInteger('fee')->unsigned()->nullable(false);

            $table->boolean('completed');
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE payments MODIFY account_address varbinary(6)");
            DB::statement("ALTER TABLE payments MODIFY account_hashin varbinary(32)");
            DB::statement("ALTER TABLE payments MODIFY account_hashout varbinary(32)");
            DB::statement("ALTER TABLE payments MODIFY tx_id varbinary(8)");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('payments');
    }
}
