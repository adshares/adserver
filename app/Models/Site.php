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

use Adshares\Adserver\Http\Controllers\Simulator;
use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int user_id
 * @property string name
 * @property array|null|string site_requires
 * @property array|null|string site_excludes
 * @method static Site create($input = null)
 */
class Site extends Model
{
    use Ownership;
    use SoftDeletes;
    /**
     * Template for html code, which should be pasted for each ad unit
     */
    const PAGE_CODE_TEMPLATE = '<div data-pub="$publisherId" data-zone="$zoneId" '
    . 'style="width:$widthpx;height:$heightpx;display: block;margin: 0 auto;background-color: #FAA"></div>';
    public static $rules = [
        'name' => 'required|max:64',
        'primary_language' => 'required|max:2',
        'status' => 'required|numeric',
    ];
    protected $casts = [
        'site_requires' => 'json',
        'site_excludes' => 'json',
//        'status' => 'boolean',
    ];
    /** @var string[] */
    protected $fillable = [
        'name',
        'status',
        'primary_language',
        'filtering',
    ];
    /** @var string[] */
    protected $hidden = [
        'deleted_at',
        'site_requires',
        'site_excludes',
        'zones',
    ];
    protected $appends = [
        'ad_units',
        'filtering',
        'page_code_common',
    ];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function addZones(array $data): void
    {
        $records = array_map(function ($zone) {
            $zone['name'] = $zone['short_headline'];
            unset($zone['short_headline']);

            $size = Simulator::getZoneTypes()[$zone['size']['size']];
            $zone['width'] = $size['width'];
            $zone['height'] = $size['height'];

            return $zone;
        }, $data);

        $this->zones()->createMany($records);
    }

    public function getAdUnitsAttribute(): array
    {
        return array_map(function (Zone $zone) {
            return [
                'page_code' => $this->getAdUnitPageCode($zone),
                'short_headline' => $zone->name,
                'size' => [
                    'name' => $zone->name,
                    'width' => $zone->width,
                    'height' => $zone->height,
                ],
            ];
        }, $this->zones->all());
    }

    public function setFilteringAttribute(array $data): void
    {
        $this->site_requires = $data['requires'];
        $this->site_excludes = $data['excludes'];
    }

    public function getFilteringAttribute(): array
    {
        return [
            'requires' => $this->site_requires,
            'excludes' => $this->site_excludes,
        ];
    }

    /**
     * @return string html code which links to script finding proper advertisement
     */
    public function getPageCodeCommonAttribute(): string
    {
        $serverUrl = config('app.url');

        return "<script src=\"$serverUrl/supply/find.js\" async></script>";
    }

    /**
     * @param $zone Zone AdUnit data
     *
     * @return string html code for specific AdUnit
     */
    private function getAdUnitPageCode(Zone $zone): string
    {
        $replaceArr = [
            '$publisherId' => $this->user_id,
            '$zoneId' => $zone->id,
            '$width' => $zone->width,
            '$height' => $zone->height,
        ];

        return strtr(self::PAGE_CODE_TEMPLATE, $replaceArr);
    }
}
