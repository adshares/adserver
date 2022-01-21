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

use Adshares\Adserver\Models\Banner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMimeTypeToBanners extends Migration
{
    /**
     * Maximal mime-type length based on RFC 4288
     */
    private const MAXIMAL_MIME_TYPE_LENGTH = 127;

    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('creative_mime_type', self::MAXIMAL_MIME_TYPE_LENGTH)->after('creative_type')->nullable();
        });

        DB::table('banners')->where('creative_type', Banner::TEXT_TYPE_DIRECT_LINK)->update(
            ['creative_mime_type' => 'text/plain']
        );
        DB::table('banners')->where('creative_type', Banner::TEXT_TYPE_HTML)->update(
            ['creative_mime_type' => 'text/html']
        );
        DB::update(
            "UPDATE banners SET creative_mime_type='image/png' WHERE creative_contents LIKE 0x89504E470D0A1A0A25;"
        );
        DB::update("UPDATE banners SET creative_mime_type='image/jpeg' WHERE creative_contents LIKE 0xFFD8FF25;");
        DB::update("UPDATE banners SET creative_mime_type='image/gif' WHERE creative_contents LIKE 0x4749463825;");

        Schema::table('banners', function (Blueprint $table) {
            $table->string('creative_mime_type', self::MAXIMAL_MIME_TYPE_LENGTH)->nullable(false)->change();
        });

        Schema::table('network_banners', function (Blueprint $table) {
            $table->string('mime_type', self::MAXIMAL_MIME_TYPE_LENGTH)->after('type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('network_banners', function (Blueprint $table) {
            $table->dropColumn('mime_type');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('creative_mime_type');
        });
    }
}
