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
    public function up(): void
    {
        Schema::create('site_reject_reasons', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $rejectReasonRows = DB::select('SELECT DISTINCT (reject_reason) FROM sites');
        foreach ($rejectReasonRows as $rejectReasonRow) {
            $reason = $rejectReasonRow->reject_reason;
            if (null === $reason) {
                continue;
            }
            DB::insert(
                'INSERT INTO site_reject_reasons (reject_reason, created_at, updated_at) VALUES (?, NOW(), NOW())',
                [$reason],
            );
        }

        Schema::table('sites_rejected_domains', function (Blueprint $table) {
            $table->unsignedSmallInteger('reject_reason_id')->nullable();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedSmallInteger('reject_reason_id')->nullable();
        });
        DB::update(
            'UPDATE sites s JOIN site_reject_reasons r on s.reject_reason = r.reject_reason SET s.reject_reason_id=r.id'
        );
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('reject_reason', 255)->nullable();
        });
        DB::update(
            'UPDATE sites s JOIN site_reject_reasons r on s.reject_reason_id = r.id SET s.reject_reason=r.reject_reason'
        );
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('reject_reason_id');
        });
        Schema::table('sites_rejected_domains', function (Blueprint $table) {
            $table->dropColumn('reject_reason_id');
        });
        Schema::dropIfExists('site_reject_reasons');
    }
};
