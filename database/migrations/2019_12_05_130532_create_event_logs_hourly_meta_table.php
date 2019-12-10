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

use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventLogsHourlyMetaTable extends Migration
{
    public function up(): void
    {
        Schema::create(
            'event_logs_hourly_metas',
            function (Blueprint $table) {
                $table->bigInteger('id')->primary();
                $table->timestamps();
                $table->unsignedTinyInteger('status')->default(NetworkCaseLogsHourlyMeta::STATUS_INVALID)->index();
                $table->unsignedMediumInteger('process_time_last')->nullable();
                $table->unsignedSmallInteger('process_count')->default(0);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs_hourly_metas');
    }
}
