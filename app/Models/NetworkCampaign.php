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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkCampaign extends Model
{
    use AutomateMutators;
    use BinHex;

    const STATUS_ACTIVE = 1;

    const STATUS_DELETED = 2;

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
}
