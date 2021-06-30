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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Supply\Domain\ValueObject\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class NetworkCampaign extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $dates = [
        'date_start',
        'date_end',
        'source_created_at',
        'source_updated_at',
    ];

    protected $casts = [
        'targeting_requires' => 'json',
        'targeting_excludes' => 'json',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'demand_campaign_id',
        'publisher_id',
        'source_created_at',
        'source_updated_at',
        'source_host',
        'source_address',
        'source_version',
        'landing_url',
        'max_cpm',
        'max_cpc',
        'budget',
        'date_start',
        'date_end',
        'targeting_requires',
        'targeting_excludes',
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'demand_campaign_id' => 'BinHex',
        'publisher_id' => 'BinHex',
    ];

    public static function getTableName()
    {
        return with(new static())->getTable();
    }

    public function banners(): HasMany
    {
        return $this->hasMany(NetworkBanner::class)->orderBy('uuid');
    }

    public function fetchActiveBanners()
    {
        return $this->banners()->where('status', Status::STATUS_ACTIVE)->get();
    }

    public static function findSupplyIdsByDemandIdsAndAddress(array $demandIds, string $sourceAddress): array
    {
        $binDemandIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $demandIds
        );

        $campaigns = self::whereIn('demand_campaign_id', $binDemandIds)
            ->where('source_address', $sourceAddress)
            ->select('uuid', 'demand_campaign_id')
            ->get();

        $ids = [];

        foreach ($campaigns as $campaign) {
            $ids[$campaign->demand_campaign_id] = $campaign->uuid;
        }

        return $ids;
    }
}
