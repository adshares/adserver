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

use Adshares\Adserver\Utilities\SiteUtils;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixMetaverseSiteName extends Migration
{
    public function up(): void
    {
        $rows = DB::select("SELECT id, name FROM sites WHERE name LIKE 'scene-%.decentraland.org';");
        foreach ($rows as $row) {
            $name = SiteUtils::extractNameFromDecentralandDomain($row->name);
            DB::table('sites')
                ->where('id', $row->id)
                ->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        $rows = DB::select("SELECT id, domain FROM sites WHERE domain LIKE 'scene-%.decentraland.org';");
        foreach ($rows as $row) {
            DB::table('sites')
                ->where('id', $row->id)
                ->update(['name' => $row->domain]);
        }
    }
}
