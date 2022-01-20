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

//        $campaigns = DB::table('campaigns')->whereNull('secret')->get(['id']);
//        foreach ($campaigns as $campaign) {
//            DB::table('campaigns')->where('id', $campaign->id)->update(
//                ['secret' => Utils::base64Encoded16BytesSecret()]
//            );
//        }
//        Schema::table('banners', function (Blueprint $table) {
//            $table->string('creative_mime_type', self::MAXIMAL_MIME_TYPE_LENGTH)->nullable(false)->change();
//        });

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
