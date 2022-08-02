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

class InsertConfigSettings extends Migration
{
    public function up(): void
    {
        $now = new DateTimeImmutable();
        $timestamps = [
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $configurationEntries = [
            [
                'key' => Config::HOT_WALLET_MIN_VALUE,
                'value' => config('app.hotwallet_min_value') ?? '2000000000000000',
            ],
            [
                'key' => Config::HOT_WALLET_MAX_VALUE,
                'value' => config('app.hotwallet_max_value') ?? '50000000000000000',
            ],
            [
                'key' => Config::ADSERVER_NAME,
                'value' => config('app.adserver_name') ?? 'AdServer',
            ],
            [
                'key' => Config::TECHNICAL_EMAIL,
                'value' => config('app.technical_email') ?? 'mail@example.com',
            ],
            [
                'key' => Config::SUPPORT_EMAIL,
                'value' => config('app.support_email') ?? 'mail@example.com',
            ],
        ];

        foreach ($configurationEntries as $configurationEntry) {
            DB::table('configs')->insert(
                array_merge(
                    $timestamps,
                    $configurationEntry,
                )
            );
        }
    }

    public function down(): void
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
