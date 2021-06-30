<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCountryToNetworkImpressions extends Migration
{
    public function up(): void
    {
        Schema::table(
            'network_impressions',
            function (Blueprint $table) {
                $table->string('country', 8)->nullable();
            }
        );

        DB::update("UPDATE network_impressions SET country=JSON_UNQUOTE(JSON_EXTRACT(user_data, '$.user.country'))");
    }

    public function down(): void
    {
        Schema::table(
            'network_impressions',
            function (Blueprint $table) {
                $table->dropColumn('country');
            }
        );
    }
}
