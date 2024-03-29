<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int id
 * @property string uuid
 * @property string source_address
 * @property string medium
 * @property string|null vendor
 * @mixin Builder
 */
class NetworkCampaign extends Model
{
    use AutomateMutators;
    use BinHex;
    use HasFactory;

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
        'medium',
        'vendor',
        'targeting_requires',
        'targeting_excludes',
        'status',
    ];

    protected $hidden = [
        'id',
    ];

    protected array $traitAutomate = [
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

    /**
     * @return Collection|NetworkBanner[]
     */
    public function fetchActiveBanners(): Collection
    {
        return $this->banners()->where('status', Status::STATUS_ACTIVE)->get();
    }

    public static function findSupplyIdsByDemandIdsAndAddress(array $demandIds, string $sourceAddress): array
    {
        $binDemandIds = array_map(fn(string $item) => hex2bin($item), $demandIds);

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

    public static function fetchByDemandIdsAndAddress(array $demandIds, string $sourceAddress): Collection
    {
        $binDemandIds = array_map(fn(string $item) => hex2bin($item), $demandIds);
        return self::query()
            ->whereIn('demand_campaign_id', $binDemandIds)
            ->where('source_address', $sourceAddress)
            ->get()
            ->keyBy('demand_campaign_id');
    }

    public static function fetchActiveCampaignsFromHost(string $sourceAddress): Collection
    {
        return self::query()
            ->select('network_campaigns.*')
            ->where('network_campaigns.source_address', $sourceAddress)
            ->where('network_campaigns.status', Status::STATUS_ACTIVE)
            ->join('network_banners', 'network_campaigns.id', '=', 'network_banners.network_campaign_id')
            ->where('network_banners.status', Status::STATUS_ACTIVE)
            ->get();
    }
}
