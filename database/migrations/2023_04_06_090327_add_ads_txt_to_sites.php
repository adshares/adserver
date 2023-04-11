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
        DB::insert(
            <<<SQL
INSERT INTO site_reject_reasons
    (id, reject_reason, created_at, updated_at)
VALUES
    (65000, 'File ads.txt is missing', NOW(), NOW());
SQL
        );

        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('ads_txt_check_at')->nullable();
            $table->timestamp('ads_txt_confirmed_at')->index()->nullable();
            $table->unsignedTinyInteger('ads_txt_fails')->nullable(false)->default(0);
        });
        Schema::table('event_logs', function (Blueprint $table) {
            $table->string('medium', 16)->default('web');
            $table->string('vendor', 32)->nullable();
            $table->boolean('ads_txt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_logs', function (Blueprint $table) {
            $table->dropColumn(['medium', 'vendor', 'ads_txt']);
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['ads_txt_check_at', 'ads_txt_confirmed_at', 'ads_txt_fails']);
        });

        DB::delete('DELETE FROM site_reject_reasons WHERE id = 65000');
    }
};
