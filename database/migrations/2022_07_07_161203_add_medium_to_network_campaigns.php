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

class AddMediumToNetworkCampaigns extends Migration
{
    private const MEDIUM_METAVERSE = 'metaverse';
    private const METAVERSE_VENDORS = [
        'cryptovoxels' => 'cryptovoxels.com',
        'decentraland' => 'decentraland.org',
    ];

    public function up(): void
    {
        Schema::table('network_campaigns', function (Blueprint $table) {
            $table->string('medium', 16)->default('web');
            $table->string('vendor', 32)->nullable();
        });

        $rows = DB::select(<<<SQL
SELECT id, targeting_requires->'$.site.domain' AS domains
FROM network_campaigns
WHERE JSON_EXTRACT(targeting_requires, '$.site.domain') IS NOT NULL;
SQL
            );
        foreach ($rows as $row) {
            $domains = json_decode($row->domains, true);

            foreach (self::METAVERSE_VENDORS as $vendor => $vendorDomain) {
                $matchesCount = 0;
                foreach ($domains as $domain) {
                    if (!str_ends_with($domain, $vendorDomain)) {
                        break;
                    }
                    ++$matchesCount;
                }
                if (count($domains) === $matchesCount) {
                    DB::table('network_campaigns')
                        ->where('id', $row->id)
                        ->update([
                            'medium' => self::MEDIUM_METAVERSE,
                            'vendor' => $vendor,
                        ]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('network_campaigns', function (Blueprint $table) {
            $table->dropColumn(['medium', 'vendor']);
        });
    }
}
