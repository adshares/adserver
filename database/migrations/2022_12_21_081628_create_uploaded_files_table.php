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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maximal mime-type length based on RFC 4288
     */
    private const MAXIMAL_MIME_TYPE_LENGTH = 127;

    public function up(): void
    {
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 32);
            $table->string('medium', 16)->default('web');
            $table->string('vendor', 32)->nullable();
            $table->string('mime', self::MAXIMAL_MIME_TYPE_LENGTH);
            $table->string('scope', 16)->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });
        DB::statement('ALTER TABLE uploaded_files MODIFY uuid VARBINARY(16) NOT NULL');
        DB::statement('ALTER TABLE uploaded_files ADD content LONGBLOB');
        Schema::table('uploaded_files', static function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};
