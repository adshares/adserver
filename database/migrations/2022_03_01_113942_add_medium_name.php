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

use Adshares\Adserver\ViewModel\MediumName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMediumName extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('medium', 16)->after('budget')->default(MediumName::Web->value);
            $table->string('vendor', 32)->after('medium')->nullable();
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->string('medium', 16)->after('reassess_available_at')->default(MediumName::Web->value);
            $table->string('vendor', 32)->after('medium')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['medium', 'vendor']);
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['medium', 'vendor']);
        });
    }
}
