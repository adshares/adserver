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
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConversionDefinitionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_definitions', static function (Blueprint $table) {
            $table->increments('id');
            $table->binary('uuid', 16);
            $table->bigInteger('campaign_id')->unsigned();
            $table->string('name', 255);
            $table->string('budget_type', 20); // in_budget, out_of_budget
            $table->string('event_type', 50); // e.g. register, click, buy
            $table->string('type')->default('basic'); // basic, advanced
            $table->bigInteger('value')->nullable();
            $table->boolean('is_value_mutable')->default(0);
            $table->bigInteger('limit')->nullable();
            $table->bigInteger('cost')->default(0);
            $table->unsignedInteger('occurrences')->default(0);
            $table->boolean('is_repeatable')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE conversion_definitions MODIFY uuid varbinary(16)');
        }

        Schema::table('conversion_definitions', static function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_definitions');
    }
}
