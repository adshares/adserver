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

use Adshares\Supply\Domain\ValueObject\HostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const MAXIMAL_FAILED_CONNECTION_COUNT = 10;

    public function up(): void
    {
        Schema::table('network_hosts', function (Blueprint $table) {
            $table->timestamp('last_synchronization')->after('last_broadcast')->nullable()->default(null);
            $table->string('info_url')->default('');
            $allowedStatuses = array_map(fn($status) => $status->value, HostStatus::cases());
            $table->enum('status', $allowedStatuses)->default(HostStatus::Initialization->value)->index();
            $table->string('error')->nullable();
        });
        DB::update(
            'UPDATE network_hosts SET status=:status WHERE failed_connection>=:failed',
            [
                ':failed' => self::MAXIMAL_FAILED_CONNECTION_COUNT,
                ':status' => HostStatus::Unreachable->value,
            ]
        );
    }

    public function down(): void
    {
        Schema::table('network_hosts', function (Blueprint $table) {
            $table->dropColumn(['last_synchronization', 'info_url', 'status', 'error']);
        });
    }
};
