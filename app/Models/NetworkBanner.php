<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\Builder;
use function array_map;
use function hex2bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property NetworkCampaign campaign
 * @mixin Builder
 */
class NetworkBanner extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'network_campaign_id',
        'source_created_at',
        'source_updated_at',
        'serve_url',
        'click_url',
        'view_url',
        'type',
        'checksum',
        'width',
        'height',
        'status',
        'classification',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id',
        'network_campaign_id',
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'checksum' => 'BinHex',
    ];

    protected $casts = [
        'classification' => 'json',
    ];

    public static function getTableName()
    {
        return with(new static())->getTable();
    }

    public static function findByUuid(string $bannerId): ?self
    {
        return self::where('uuid', hex2bin($bannerId))->first();
    }

    public static function fetch(int $limit, int $offset)
    {
        $query = self::skip($offset)->take($limit)->orderBy('network_banners.id', 'desc');
        $query->join('network_campaigns', 'network_banners.network_campaign_id', '=', 'network_campaigns.id');
        $query->select(
            'network_banners.id',
            'network_banners.serve_url',
            'network_banners.type',
            'network_banners.width',
            'network_banners.height',
            'network_campaigns.source_host',
            'network_campaigns.budget',
            'network_campaigns.max_cpm',
            'network_campaigns.max_cpc'
        );

        return $query->get();
    }

    public static function fetchCount(): int
    {
        return (new NetworkBanner())->count();
    }
    
    public static function findIdsByUuids(array $publicUuids)
    {
        $binPublicIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $publicUuids
        );

        $banners = self::whereIn('uuid', $binPublicIds)
            ->select('id', 'uuid')
            ->get();

        $ids = [];

        foreach ($banners as $banner) {
            $ids[$banner->uuid] = $banner->id;
        }

        return $ids;
    }

    public function getAdSelectArray(): array
    {
        return [
            'banner_id' => $this->uuid,
            'banner_size' => $this->width.'x'.$this->height,
            'keywords' => [
                'type' => $this->type,
            ],
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NetworkCampaign::class, 'network_campaign_id');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Classification::class);
    }

    public static function fetchByPublicId(string $publicId): ?self
    {
        return self::where('uuid', hex2bin($publicId))->first();
    }
}
