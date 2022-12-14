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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Zone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(
            'banners',
            function (Blueprint $table) {
                $table->string('creative_size', 16)->default('')->after('creative_height');
            }
        );
        DB::update('UPDATE `banners` SET `creative_size` = CONCAT(`creative_width`, "x", `creative_height`)');
        Schema::table(
            'banners',
            function (Blueprint $table) {
                $table->dropColumn('creative_width');
            }
        );
        Schema::table(
            'banners',
            function (Blueprint $table) {
                $table->dropColumn('creative_height');
            }
        );

        Schema::table(
            'network_banners',
            function (Blueprint $table) {
                $table->string('size', 16)->default('')->after('height');
            }
        );
        DB::update('UPDATE `network_banners` SET `size` = CONCAT(`width`, "x", `height`)');

        Schema::table(
            'network_banners',
            function (Blueprint $table) {
                $table->dropColumn('width');
            }
        );
        Schema::table(
            'network_banners',
            function (Blueprint $table) {
                $table->dropColumn('height');
            }
        );

        Schema::table(
            'zones',
            function (Blueprint $table) {
                $table->string('size', 16)->default('')->after('height');
                $table->string('type')->default(Zone::TYPE_DISPLAY)->change();
            }
        );
        DB::update(
            sprintf(
                'UPDATE `zones` SET `size` = CONCAT(`width`, "x", `height`), `type` = \'%s\'',
                Zone::TYPE_DISPLAY
            )
        );

        Schema::table(
            'zones',
            function (Blueprint $table) {
                $table->dropColumn('width');
            }
        );
        Schema::table(
            'zones',
            function (Blueprint $table) {
                $table->dropColumn('height');
            }
        );
        Schema::table(
            'zones',
            function (Blueprint $table) {
                $table->dropColumn('label');
            }
        );
    }

    public function down(): void
    {
        DB::delete("DELETE FROM `banners` WHERE `creative_size` NOT REGEXP '^[0-9]+x[0-9]+$';");
        Schema::table(
            'banners',
            function (Blueprint $table) {
                $table->integer('creative_height')->after('creative_size');
                $table->integer('creative_width')->after('creative_size');
            }
        );
        DB::update(
            'UPDATE `banners` '
            . 'SET '
            . '`creative_width` = SUBSTRING_INDEX(`creative_size`, "x", 1), '
            . '`creative_height` = SUBSTRING_INDEX(`creative_size`, "x", -1)'
        );
        Schema::table(
            'banners',
            function (Blueprint $table) {
                $table->dropColumn('creative_size');
            }
        );

        DB::delete("DELETE FROM `network_banners` WHERE `size` NOT REGEXP '^[0-9]+x[0-9]+$';");
        Schema::table(
            'network_banners',
            function (Blueprint $table) {
                $table->integer('height')->after('size');
                $table->integer('width')->after('size');
            }
        );
        DB::update(
            'UPDATE `network_banners` '
            . 'SET `width` = SUBSTRING_INDEX(`size`, "x", 1), `height` = SUBSTRING_INDEX(`size`, "x", -1)'
        );
        Schema::table(
            'network_banners',
            function (Blueprint $table) {
                $table->dropColumn('size');
            }
        );

        Schema::table(
            'zones',
            function (Blueprint $table) {
                $table->integer('height')->after('size');
                $table->integer('width')->after('size');
                $table->string('label')->after('type');
            }
        );

        DB::delete("DELETE FROM `zones` WHERE `size` NOT REGEXP '^[0-9]+x[0-9]+$';");
        DB::update(
            'UPDATE `zones` '
            . 'SET `width` = SUBSTRING_INDEX(`size`, "x", 1), `height` = SUBSTRING_INDEX(`size`, "x", -1), '
            . '`label` = `size`'
        );

        Schema::table(
            'zones',
            function (Blueprint $table) {
                $table->dropColumn('size');
            }
        );
    }
};
