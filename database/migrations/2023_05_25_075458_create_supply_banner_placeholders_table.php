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

return new class extends Migration {
    /**
     * Maximal mime-type length based on RFC 4288
     */
    private const MAXIMAL_MIME_TYPE_LENGTH = 127;

    public function up(): void
    {
        Schema::create(
            'supply_banner_placeholders',
            function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->softDeletes();
                $table->string('medium', 16)->default('web');
                $table->string('vendor', 32)->nullable();
                $table->string('size', 16)->default('');
                $table->string('type', 32);
                $table->string('mime', self::MAXIMAL_MIME_TYPE_LENGTH);
                $table->boolean('is_default')->default(false);
            },
        );
        DB::statement('ALTER TABLE supply_banner_placeholders ADD uuid VARBINARY(16) NOT NULL AFTER id');
        DB::statement('ALTER TABLE supply_banner_placeholders ADD parent_uuid VARBINARY(16) AFTER uuid');
        DB::statement('ALTER TABLE supply_banner_placeholders ADD content LONGBLOB NOT NULL');
        DB::statement('ALTER TABLE supply_banner_placeholders ADD checksum VARBINARY(20) NOT NULL');
        Schema::table(
            'supply_banner_placeholders',
            function (Blueprint $table) {
                $table->unique('uuid');
                $table->index('parent_uuid');
            },
        );

        Schema::create(
            'network_missed_cases',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('created_at')->useCurrent();
                $table->unsignedBigInteger('network_impression_id');
            }
        );
        DB::statement('ALTER TABLE network_missed_cases ADD case_id VARBINARY(16) NOT NULL AFTER created_at');
        DB::statement('ALTER TABLE network_missed_cases ADD publisher_id VARBINARY(16) NOT NULL');
        DB::statement('ALTER TABLE network_missed_cases ADD site_id VARBINARY(16) NOT NULL');
        DB::statement('ALTER TABLE network_missed_cases ADD zone_id VARBINARY(16) NOT NULL');
        DB::statement('ALTER TABLE network_missed_cases ADD banner_id VARBINARY(16) NOT NULL');
        Schema::table(
            'network_missed_cases',
            function (Blueprint $table) {
                $table->unique('case_id');
                $table->index('created_at');
            }
        );

        Schema::table('network_case_logs_hourly', function (Blueprint $table) {
            $table->unsignedInteger('views_missed')->default(0);
        });
        Schema::table('network_case_logs_hourly_stats', function (Blueprint $table) {
            $table->unsignedInteger('views_missed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropColumns('network_case_logs_hourly_stats', ['views_missed']);
        Schema::dropColumns('network_case_logs_hourly', ['views_missed']);
        Schema::dropIfExists('network_missed_cases');
        Schema::dropIfExists('supply_banner_placeholders');
    }
};
