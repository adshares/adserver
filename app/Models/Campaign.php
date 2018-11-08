<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property string uuid
 * @property int created_at
 * @property int updated_at
 * @property int deleted_at
 * @property int user_id
 * @property string landing_url
 * @property \DateTime time_start
 * @property \DateTime time_end
 * @property int status
 * @property string name
 * @property array|null|string strategy_name
 * @property float bid
 * @property float budget
 * @property array|null|string targeting_requires
 * @property array|null|string targeting_excludes
 * @property Banner[]|Collection banners
 */
class Campaign extends Model
{
    use Ownership;
    use SoftDeletes;
    use AutomateMutators;
    use BinHex;

    const STATUS_DRAFT = 0;
    const STATUS_INACTIVE = 1;
    const STATUS_ACTIVE = 2;

    public static $rules = [
//        'name' => 'required|max:255',
//        'landing_url' => 'required|max:1024',
//        'strategy_name' => 'in:CPC,CPM',
//        'bid' => 'required:numeric',
//        'budget' => 'required:numeric',
    ];

    protected $dates = [
        'deleted_at',
        'time_start',
        'time_end',
    ];

    protected $casts = [
        'time_start' => 'string',
        'time_end' => 'string',
        'targeting_requires' => 'json',
        'targeting_excludes' => 'json',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'landing_url',
        'time_start',
        'time_end',
        'require_count',
        'user_id',
        'name',
        'status',
        'budget',
        'bid',
        'strategy_name',
        'basic_information',
        'targeting_requires',
        'targeting_excludes',
        'classification_status',
        'classification_tags',
    ];

    protected $visible = [
        'id',
        'uuid',
        'created_at',
        'updated_at',
        'landing_url',
        'time_start',
        'time_end',
        'status',
        'name',
        'strategy_name',
        'bid',
        'budget',
        'classification_status',
        'classification_tags',
        'basic_information',
        'targeting',
        'ads',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    /** @var array Aditional fields to be included in collections */
    protected $appends = ['basic_information', 'targeting', 'ads'];

    public function banners()
    {
        return $this->hasMany(Banner::class);
    }

    public function getAdsAttribute()
    {
        foreach ($this->banners as &$banner) {
            $size = $banner->creative_width . 'x' . $banner->creative_height;
            $banner['type'] = $banner['creative_type'] === 'image' ? 0 : 1;
            $banner['size'] = array_search($size, Zone::ZONE_SIZE);
        }

        return $this->banners;
    }

    public function getTargetingAttribute()
    {
        return [
            "requires" => $this->targeting_requires,
            "excludes" => $this->targeting_excludes,
        ];
    }

    public function setBasicInformationAttribute(array $value)
    {
        $this->status = $value["status"];
        $this->name = $value["name"];
        $this->landing_url = $value["target_url"];
        $this->strategy_name = $value["bid_strategy_name"];
        $this->bid = $value["bid_value"];
        $this->budget = $value["budget"];
        $this->time_start = $value["date_start"];
        $this->time_end = $value["date_end"] ?? null;
    }

    public function getBasicInformationAttribute()
    {
        return [
            "status" => $this->status,
            "name" => $this->name,
            "target_url" => $this->landing_url,
            "bid_strategy_name" => $this->strategy_name,
            "bid_value" => $this->bid,
            "budget" => $this->budget,
            "date_start" => $this->time_start,
            "date_end" => $this->time_end,
        ];
    }

    public function getBannersUrls(): array
    {
        $urls = [];

        foreach ($this->banners as $banner) {
            $urls[] = $banner->toArray()['serve_url'];
        }

        return $urls;
    }

    public static function isStatusAllowed(int $status): bool
    {
        return in_array($status, [self::STATUS_DRAFT, self::STATUS_INACTIVE, self::STATUS_ACTIVE]);
    }
}
