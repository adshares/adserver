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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->bigInteger('max_cpm')->nullable()->default(null)->change();
            $table->bigInteger('max_cpc')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::update('UPDATE `campaigns` SET `max_cpm` = 0 WHERE `max_cpm` IS NULL');
        DB::update('UPDATE `campaigns` SET `max_cpc` = 0 WHERE `max_cpc` IS NULL');
        Schema::table('campaigns', function (Blueprint $table) {
            $table->bigInteger('max_cpm')->nullable(false)->default(0)->change();
            $table->bigInteger('max_cpc')->nullable(false)->default(0)->change();
        });
    }
};
