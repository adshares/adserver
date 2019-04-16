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
use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;

class BonusConfig extends Migration
{
    public function up()
    {
        $bonusEnalbed = DB::table('configs')->where('key', Config::BONUS_NEW_USER_ENABLED)->first();
        if ($bonusEnalbed === null) {
            DB::table('configs')->insert(
                [
                    'key' => Config::BONUS_NEW_USER_ENABLED,
                    'value' => 0,
                    'created_at' => new DateTime(),
                ]
            );
        }

        $bonusAmount = DB::table('configs')->where('key', Config::BONUS_NEW_USER_AMOUNT)->first();
        if ($bonusAmount === null) {
            DB::table('configs')->insert(
                [
                    'key' => Config::BONUS_NEW_USER_AMOUNT,
                    'value' => 0,
                    'created_at' => new DateTime(),
                ]
            );
        }
    }

    public function down()
    {
    }
}
