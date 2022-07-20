<?php
/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
use Illuminate\Support\Facades\DB;

class MoveEnvToConfig extends Migration
{
    private const CE_LICENSE_ACCOUNT = '0001-00000024-FF89';

    public function up(): void
    {
        $keys = array_map(
            fn($item) => sprintf("'%s'", $item),
            [Config::LICENCE_ACCOUNT, Config::LICENCE_RX_FEE, Config::LICENCE_TX_FEE]
        );

        $sql = sprintf('DELETE FROM configs WHERE `key` IN (%s);', implode(',', $keys));

        DB::delete($sql);
    }

    public function down(): void
    {
        Config::updateOrCreate(
            ['key' => Config::LICENCE_ACCOUNT],
            ['value' => self::CE_LICENSE_ACCOUNT]
        );
        Config::updateOrCreate(
            ['key' => Config::LICENCE_RX_FEE],
            ['value' => '0.01']
        );
        Config::updateOrCreate(
            ['key' => Config::LICENCE_TX_FEE],
            ['value' => '0.01']
        );
    }
}
