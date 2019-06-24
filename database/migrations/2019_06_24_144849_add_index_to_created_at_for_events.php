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

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\NetworkEventLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToCreatedAtForEvents extends Migration
{
    public function up(): void
    {
        Schema::table('event_logs',

            static function (Blueprint $table) {
                $table->index('created_at', EventLog::INDEX_CREATED_AT);
            });

        Schema::table('network_event_logs',
            static function (Blueprint $table) {
                $table->index('created_at', NetworkEventLog::INDEX_CREATED_AT);
                $table->index('updated_at', NetworkEventLog::INDEX_UPDATED_AT);
            });
    }

    public function down(): void
    {
        Schema::table('event_logs',
            static function (Blueprint $table) {
                $table->dropIndex(EventLog::INDEX_CREATED_AT);
            });

        Schema::table('network_event_logs',
            static function (Blueprint $table) {
                $table->dropIndex(NetworkEventLog::INDEX_CREATED_AT);
                $table->dropIndex(NetworkEventLog::INDEX_UPDATED_AT);
            });
    }
}

