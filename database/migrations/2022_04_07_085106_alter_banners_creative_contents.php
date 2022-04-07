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
use Illuminate\Database\Migrations\Migration;

class AlterBannersCreativeContents extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `banners` MODIFY COLUMN `creative_contents` LONGBLOB;');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM `banners` where OCTET_LENGTH(`creative_contents`) > 16 * 1024 * 1024;');
        DB::statement('ALTER TABLE `banners` MODIFY COLUMN `creative_contents` MEDIUMBLOB;');
    }
}
