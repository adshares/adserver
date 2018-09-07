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
use Illuminate\Support\Facades\Schema;

class CreateUsersAdserversWallets extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('users_adserver_wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();

            $table->bigInteger('user_id')->unsigned()->unique();

            $table->binary('adshares_address', 6)->nullable();
            $table->string('payment_memo', 48)->nullable();

            $table->decimal('total_funds', 20, 9)->default(0);
            $table->decimal('total_funds_in_currency', 20, 2)->default(0);
            $table->decimal('total_funds_change', 20, 9)->default(0);

            $table->timestamp('last_payment_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE users_adserver_wallets MODIFY adshares_address varbinary(6)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('users_adserver_wallets');
    }
}
