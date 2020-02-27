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

use Adshares\Adserver\Models\ReportMeta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateReportMetasTable extends Migration
{
    public function up(): void
    {
        Schema::create(
            'report_metas',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
                $table->softDeletes();
                $table->bigInteger('user_id')->index();
                $table->binary('uuid');
                $table->enum('type', ReportMeta::ALLOWED_TYPES);
                $table->enum(
                    'state',
                    [
                        ReportMeta::STATE_PREPARING,
                        ReportMeta::STATE_READY,
                        ReportMeta::STATE_DELETED,
                    ]
                )->default(ReportMeta::STATE_PREPARING);
                $table->string('name', ReportMeta::NAME_LENGTH_MAX);

                $table->index('updated_at');
            }
        );

        DB::statement('ALTER TABLE report_metas MODIFY uuid varbinary(16) NOT NULL');

        Schema::table(
            'report_metas',
            function (Blueprint $table) {
                $table->unique('uuid');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('report_metas');
    }
}
