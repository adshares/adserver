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

class UpdateConfigEntryNameForColdWallet extends Migration
{
    public function up()
    {
        $coldWalletIsActive = DB::table('configs')->where('key', Config::COLD_WALLET_IS_ACTIVE)->first();
        if ($coldWalletIsActive === null) {
            $hotWalletIsActive = DB::table('configs')->where('key', 'hotwallet-is-active')->first();
            if ($hotWalletIsActive === null) {
                DB::table('configs')->insert(
                    [
                        'key' => Config::COLD_WALLET_IS_ACTIVE,
                        'value' => 0,
                        'created_at' => new DateTime(),
                    ]
                );
            } else {
                DB::update(
                    'UPDATE `configs` SET `key` = ? WHERE `key` = ?',
                    [Config::COLD_WALLET_IS_ACTIVE, 'hotwallet-is-active']
                );
            }
        }

        $coldWalletAddress = DB::table('configs')->where('key', Config::COLD_WALLET_ADDRESS)->first();
        if ($coldWalletAddress === null) {
            $hotWalletAddress = DB::table('configs')->where('key', 'hotwallet-address')->first();
            if ($hotWalletAddress === null) {
                DB::table('configs')->insert(
                    [
                        'key' => Config::COLD_WALLET_ADDRESS,
                        'value' => '',
                        'created_at' => new DateTime(),
                    ]
                );
            } else {
                DB::update(
                    'UPDATE `configs` SET `key` = ? WHERE `key` = ?',
                    [Config::COLD_WALLET_ADDRESS, 'hotwallet-address']
                );
            }
        }
    }

    public function down()
    {
    }
}
