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

use Illuminate\Database\Eloquent\Model;

/**
 * Class Site.
 *
 * @property int user_id
 * @property string name
 * @property string url
 */
class Site extends Model
{
    public static $rules = [
        'name' => 'required|max:64',
    ];

    protected $casts = [
        'sites_requires' => 'json',
        'sites_excludes' => 'json',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'status',
        'zones',
        'sites_requires',
        'sites_excludes',
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at',
    ];

    /** @var array Aditional fields to be included in collections */
    protected $appends = ['adUnits', 'targetingArray'];

    public function siteExcludes()
    {
        return $this->hasMany(\Adshares\Adserver\Models\SiteExclude::class);
    }

    public function siteRequires()
    {
        return $this->hasMany(\Adshares\Adserver\Models\SiteRequire::class);
    }

    public function zones()
    {
        return $this->hasMany(\Adshares\Adserver\Models\Zone::class);
    }

    public function getAdUnitsAttribute()
    {
        $adUnits = [];
        foreach ($this->zones as $zone) {
            $adUnits[] = [
                'shortHeadline' => $zone->name,
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
            'requires' => json_decode($this->site_requires),
            'excludes' => json_decode($this->site_excludes),
        ];
    }

    public static function siteById($siteId)
    {
        return self::with([
            'siteExcludes' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
            'siteRequires' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
            'zones' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
        ])->whereNull('deleted_at')
            ->findOrFail($siteId);
    }
}
