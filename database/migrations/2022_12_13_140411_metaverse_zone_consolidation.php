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

use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Repository\FileConfigurationRepository;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $repository = new FileConfigurationRepository(storage_path('app'));
        $mediumByNameAndVendor = [];
        $scopesByMediumNameAndVendorAndSize = [];

        foreach (
            DB::select(
                'SELECT id, medium, vendor from sites WHERE medium="metaverse" AND deleted_at IS NULL'
            ) as $site
        ) {
            $uuids = [];
            $size = null;
            foreach (
                DB::select(
                    'SELECT * from zones'
                    . ' WHERE site_id=:id AND name="default" AND type="display" AND deleted_at IS NULL',
                    ['id' => $site->id]
                ) as $row
            ) {
                $uuids[] = bin2hex($row->uuid);
                $size = $row->size;
            }
            if (null === $size || empty($uuids)) {
                continue;
            }

            if (!isset($mediumByNameAndVendor[$site->medium][$site->vendor])) {
                $mediumByNameAndVendor[$site->medium][$site->vendor] =
                    $repository->fetchMedium($site->medium, $site->vendor);
            }
            if (!isset($scopesByMediumNameAndVendorAndSize[$site->medium][$site->vendor][$size])) {
                [$width, $height] = Size::toDimensions($size);
                $scopesByMediumNameAndVendorAndSize[$site->medium][$site->vendor][$size] = Size::findBestFit(
                    $mediumByNameAndVendor[$site->medium][$site->vendor],
                    $width,
                    $height,
                    Zone::DEFAULT_DEPTH,
                    Zone::DEFAULT_MINIMAL_DPI,
                );
            }

            $scopes = $scopesByMediumNameAndVendorAndSize[$site->medium][$site->vendor][$size];
            $zone = Zone::register(
                $site->id,
                $size,
                $scopes,
                'Default (legacy)',
                Zone::TYPE_DISPLAY,
            );

            $zoneUuid = '0x' . $zone->uuid;
            $uuids = '0x' . implode(',0x', $uuids);
            DB::update(
                'UPDATE network_cases SET zone_id = ' . $zoneUuid . ' WHERE zone_id IN (' . $uuids . ')'
            );
            DB::insert(
                '
                INSERT INTO network_case_logs_hourly
                SELECT
                    null as id,
                    TIMESTAMP(hour_timestamp),
                    publisher_id,
                    site_id,
                    ' . $zoneUuid . ' as zone_id,
                    domain,
                    SUM(revenue_case) AS revenue_case,
                    SUM(revenue_hour) AS revenue_hour,
                    SUM(views_all) AS views_all,
                    SUM(views) AS views,
                    SUM(views_unique) AS views_unique,
                    SUM(clicks_all) AS clicks_all,
                    SUM(clicks) AS clicks
                FROM network_case_logs_hourly n WHERE zone_id IN (' . $uuids . ')
                GROUP BY 1, 2, 3, 4, 5, 6
            '
            );
            DB::delete(
                'DELETE FROM network_case_logs_hourly WHERE zone_id IN (' . $uuids . ')'
            );
            DB::insert(
                '
                INSERT INTO network_case_logs_hourly_stats
                SELECT
                    null as id,
                    TIMESTAMP(hour_timestamp),
                    publisher_id,
                    site_id,
                    ' . $zoneUuid . ' as zone_id,
                    SUM(revenue_case) AS revenue_case,
                    SUM(revenue_hour) AS revenue_hour,
                    SUM(views_all) AS views_all,
                    SUM(views) AS views,
                    SUM(views_unique) AS views_unique,
                    SUM(clicks_all) AS clicks_all,
                    SUM(clicks) AS clicks
                FROM network_case_logs_hourly_stats n WHERE zone_id IN (' . $uuids . ')
                GROUP BY 1, 2, 3, 4, 5
            '
            );
            DB::delete(
                'DELETE FROM network_case_logs_hourly_stats WHERE zone_id IN (' . $uuids . ')'
            );
            DB::update('UPDATE zones SET deleted_at = NOW() WHERE uuid IN (' . $uuids . ')');
        }
    }

    public function down(): void
    {
        // Lack of rollback is intended.
    }
};
