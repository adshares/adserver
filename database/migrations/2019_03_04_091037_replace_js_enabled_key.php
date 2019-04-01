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

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class ReplaceJsEnabledKey extends Migration
{
    public function up(): void
    {
        DB::update(<<<SQL
UPDATE sites
SET
  site_requires = REPLACE(site_requires,
                          'js_enabled',
                          'jsenabled');
SQL
        );
        DB::update(<<<SQL
UPDATE sites
SET
  site_excludes = REPLACE(site_excludes,
                          'js_enabled',
                          'jsenabled');
SQL
        );
    }

    public function down(): void
    {
        // Lack of rollback is intended.
    }
}
