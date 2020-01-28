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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTargetingReachTable extends Migration
{
    private const TABLE_TARGETING_REACH = 'targeting_reach';

    public function up(): void
    {
        $keyLengthMaximum = strlen('site:domain:') + 255;

        Schema::create(
            self::TABLE_TARGETING_REACH,
            function (Blueprint $table) use ($keyLengthMaximum) {
                $table->bigIncrements('id');
                $table->string('key', $keyLengthMaximum)->unique();
                $table->unsignedMediumInteger('occurrences');
                $table->bigInteger('percentile_25');
                $table->bigInteger('percentile_50');
                $table->bigInteger('percentile_75');
                $table->binary('data');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE_TARGETING_REACH);
    }
}
