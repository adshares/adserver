<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('accepted_at')->after('deleted_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
        });

        DB::insert(
            <<<SQL
INSERT IGNORE INTO sites_rejected_domains (created_at, updated_at, domain)
SELECT NOW(), NOW(), domain FROM supply_blacklisted_domains;
SQL
        );
        Schema::drop('supply_blacklisted_domains');
    }

    public function down(): void
    {
        Schema::create('supply_blacklisted_domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();
        });
        DB::insert(
            <<<SQL
INSERT INTO supply_blacklisted_domains (domain)
SELECT domain FROM sites_rejected_domains;
SQL
        );

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'reject_reason']);
        });
    }
};
