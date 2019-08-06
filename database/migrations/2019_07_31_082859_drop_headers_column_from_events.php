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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropHeadersColumnFromEvents extends Migration
{
    private const TABLE_EVENT_LOGS = 'event_logs';

    private const TABLE_NETWORK_EVENT_LOGS = 'network_event_logs';

    private const COLUMN_HEADERS = 'headers';

    private const COLUMN_IP = 'ip';

    public function up(): void
    {
        Schema::table(
            self::TABLE_EVENT_LOGS,
            function (Blueprint $table) {
                $table->dropColumn([self::COLUMN_IP, self::COLUMN_HEADERS]);
            }
        );

        Schema::table(
            self::TABLE_NETWORK_EVENT_LOGS,
            function (Blueprint $table) {
                $table->dropColumn([self::COLUMN_IP, self::COLUMN_HEADERS]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            self::TABLE_EVENT_LOGS,
            function (Blueprint $table) {
                $table->binary(self::COLUMN_IP)->nullable()->after('pay_to');
                $table->json(self::COLUMN_HEADERS)->nullable()->after(self::COLUMN_IP);
            }
        );

        Schema::table(
            self::TABLE_NETWORK_EVENT_LOGS,
            function (Blueprint $table) {
                $table->binary(self::COLUMN_IP)->nullable()->after('pay_from');
                $table->json(self::COLUMN_HEADERS)->nullable()->after(self::COLUMN_IP);
            }
        );

        if (DB::isMysql()) {
            foreach ([self::TABLE_EVENT_LOGS, self::TABLE_NETWORK_EVENT_LOGS] as $table) {
                DB::statement(
                    sprintf(
                        'ALTER TABLE %s MODIFY %s VARBINARY(16)',
                        $table,
                        self::COLUMN_IP
                    )
                );
            }
        }
    }
}
