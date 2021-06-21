<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusIndexToUserLedgerEntriesTable extends Migration
{
    private const INDEX_FOR_COLUMN_TYPE = 'user_ledger_entry_status_index';

    public function up(): void
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->index('status', self::INDEX_FOR_COLUMN_TYPE);
        });
    }

    public function down(): void
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_FOR_COLUMN_TYPE);
        });
    }
}
