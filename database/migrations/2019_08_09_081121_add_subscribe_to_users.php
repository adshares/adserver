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

class AddSubscribeToUsers extends Migration
{
    private const TABLE_USERS = 'users';

    private const COLUMN_SUBSCRIBE = 'subscribe';

    public function up(): void
    {
        Schema::table(
            self::TABLE_USERS,
            function (Blueprint $table) {
                $table->boolean(self::COLUMN_SUBSCRIBE)->default(0);
            }
        );

        DB::table(self::TABLE_USERS)->whereNotNull('email_confirmed_at')->update([self::COLUMN_SUBSCRIBE => 1]);
    }

    public function down(): void
    {
        Schema::table(
            self::TABLE_USERS,
            function (Blueprint $table) {
                $table->dropColumn(self::COLUMN_SUBSCRIBE);
            }
        );
    }
}
