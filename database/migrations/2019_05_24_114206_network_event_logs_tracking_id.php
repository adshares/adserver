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

declare(strict_types = 1);

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NetworkEventLogsTrackingId extends Migration
{
    public function up(): void
    {
        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->renameColumn('user_id', 'tracking_id');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->binary('user_id', 16)->nullable();
            }
        );

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE network_event_logs MODIFY user_id varbinary(16)');
        }
    }

    public function down(): void
    {
        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->dropColumn('user_id');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->renameColumn('tracking_id', 'user_id');
            }
        );
    }
}
