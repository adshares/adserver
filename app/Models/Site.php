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

use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int user_id
 * @property string name
 */
class Site extends Model
{
    use Ownership;
    use SoftDeletes;
    public static $rules = [
        'name' => 'required|max:64',
    ];
    protected $casts = [
        'site_requires' => 'json',
        'site_excludes' => 'json',
    ];
    /** @var string[] */
    protected $fillable = [
        'user_id',
        'name',
        'status',
        'zones',
    ];
    /** @var string[] */
    protected $hidden = [
        'deleted_at',
        'site_requires',
        'site_excludes',
    ];
    /**
     * @var string[] Aditional fields to be included in collections
     */
    protected $appends = ['adUnits', 'targetingArray'];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function getAdUnitsAttribute()
    {
        $adUnits = [];

        foreach ($this->zones as $zone) {
            $adUnits[] = [
                'short_headline' => $zone->name,
                'size' => [
                    'name' => $zone->name,
                    'width' => $zone->width,
                    'height' => $zone->height,
                ],
            ];
        }

        return $adUnits;
    }

    public function getTargetingArrayAttribute()
    {
        return [
            'requires' => $this->site_requires,
            'excludes' => $this->site_excludes,
        ];
    }
}
