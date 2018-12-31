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

use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configs',
            function (Blueprint $table) {
                $table->string('key')->unique();
                $table->string('value');
                $table->timestamps();
            });

        DB::table('configs')->insert(
            [
                'key' => Config::OPERATOR_TX_FEE,
                'value' => '0.01',
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => Config::OPERATOR_RX_FEE,
                'value' => '0.01',
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => Config::LICENCE_TX_FEE,
                'value' => '0.05',
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => Config::LICENCE_RX_FEE,
                'value' => '0.05',
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => Config::LICENCE_ACCOUNT,
                'value' => '0001-00000001-8B4E',
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configs');
    }
}
