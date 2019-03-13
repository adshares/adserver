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

use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;

class InsertConfigSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('configs')->insert(
            [
                'key' => Config::HOT_WALLET_MIN_VALUE,
                'value' => config('app.adshares_wallet_min_amount') ?? '2000000000000000',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::HOT_WALLET_MAX_VALUE,
                'value' => config('app.adshares_wallet_max_amount') ?? '50000000000000000',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::ADSERVER_NAME,
                'value' => config('app.name') ?? 'AdServer',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::TECHNICAL_EMAIL,
                'value' => config('app.adshares_operator_email') ?? 'mail@example.com',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::SUPPORT_EMAIL,
                'value' => config('app.adshares_operator_email') ?? 'mail@example.com',
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
        DB::table('configs')->whereIn(
            'key',
            [
                Config::HOT_WALLET_MIN_VALUE,
                Config::HOT_WALLET_MAX_VALUE,
                Config::ADSERVER_NAME,
                Config::TECHNICAL_EMAIL,
                Config::SUPPORT_EMAIL,
            ]
        )->delete();
    }
}
