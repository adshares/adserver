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

class DropConversionGroupsTable extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('conversion_groups');
    }

    public function down(): void
    {
        Schema::create('conversion_groups', static function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('event_logs_id')->unsigned();
            $table->binary('case_id');
            $table->binary('group_id');
            $table->bigInteger('conversion_definition_id')->unsigned();
            $table->bigInteger('value')->unsigned()->nullable();
            $table->decimal('weight', 3, 2);
            $table->timestamp('created_at')->nullable(false);

            $table
                ->foreign('event_logs_id')
                ->references('id')
                ->on('event_conversion_logs')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE conversion_groups MODIFY case_id varbinary(16)');
            DB::statement('ALTER TABLE conversion_groups MODIFY group_id varbinary(16)');
        }
    }
}
