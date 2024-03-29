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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMimeToBanners extends Migration
{
    /**
     * Maximal mime-type length based on RFC 4288
     */
    private const MAXIMAL_MIME_TYPE_LENGTH = 127;

    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('creative_mime', self::MAXIMAL_MIME_TYPE_LENGTH)->after('creative_type')->nullable();
        });

        DB::table('banners')->where('creative_type', Banner::TEXT_TYPE_DIRECT_LINK)->update(
            ['creative_mime' => 'text/plain']
        );
        DB::table('banners')->where('creative_type', Banner::TEXT_TYPE_HTML)->update(
            ['creative_mime' => 'text/html']
        );
        DB::update(
            "UPDATE banners SET creative_mime='image/png' WHERE creative_contents LIKE 0x89504E470D0A1A0A25;"
        );
        DB::update("UPDATE banners SET creative_mime='image/jpeg' WHERE creative_contents LIKE 0xFFD8FF25;");
        DB::update("UPDATE banners SET creative_mime='image/gif' WHERE creative_contents LIKE 0x4749463825;");

        $rows = DB::select('SELECT id FROM banners WHERE creative_mime IS NULL;');
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($rows as $row) {
            $content = DB::selectOne('SELECT creative_contents FROM banners WHERE id=?;', [$row->id]);
            $mimeType = $fileInfo->buffer($content->creative_contents);
            DB::update(
                "UPDATE banners SET creative_mime=:mime WHERE id=:id;",
                ['mime' => $mimeType, 'id' => $row->id]
            );
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->string('creative_mime', self::MAXIMAL_MIME_TYPE_LENGTH)->nullable(false)->change();
        });

        Schema::table('network_banners', function (Blueprint $table) {
            $table->string('mime', self::MAXIMAL_MIME_TYPE_LENGTH)->after('type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('network_banners', function (Blueprint $table) {
            $table->dropColumn('mime');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('creative_mime');
        });
    }
}
