<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property int network_host_id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property int total_events_count
 * @mixin Builder
 */
class NetworkVectorsMeta extends Model
{
    protected $visible = [];

    protected $fillable = [
        'network_host_id',
        'total_events_count',
    ];

    public static function deleteByNetworkHostIds(array $networkHostIds): int
    {
        return self::whereIn('network_host_id', $networkHostIds)->delete();
    }

    public static function fetchByNetworkHostId(int $networkHostId): ?self
    {
        return self::where('network_host_id', $networkHostId)->first();
    }

    public static function fetch(): Collection
    {
        return self::all();
    }

    public static function upsert(
        int $networkHostId,
        int $totalEventsCount
    ): void {
        self::updateOrCreate(
            [
                'network_host_id' => $networkHostId,
            ],
            [
                'total_events_count' => $totalEventsCount,
            ]
        )->touch();
    }
}
