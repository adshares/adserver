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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;

class SiteSettingsConfig extends Migration
{
    public function up(): void
    {
        DB::table('configs')->insert(
            [
                'key' => Config::SITE_ACCEPT_BANNERS_MANUALLY,
                'value' => '0',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => Config::SITE_CLASSIFIER_LOCAL_BANNERS,
                'value' => Config::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT,
                'created_at' => new DateTime(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('configs')
            ->whereIn('key', [Config::SITE_CLASSIFIER_LOCAL_BANNERS, Config::SITE_ACCEPT_BANNERS_MANUALLY])
            ->delete();
    }
}
