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

use Adshares\Adserver\Http\Controllers\Simulator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Site site
 * @property int id
 */
class Zone extends Model
{
    private const CODE_TEMPLATE = <<<HTML
<div 
    data-pub="{{publisherId}}" 
    data-zone="{{zoneId}}" 
    style="width:{{width}}px;height:{{height}}px;display: block;margin: 0 auto;background-color: #FAA"></div>
HTML;
    use SoftDeletes;
    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_ARCHIVED = 2;
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED];
    public const ZONE_SIZE = [
        '728x90',
        '300x250',
        '336x280',
        '300x600',
        '320x100',
        '468x60',
        '234x60',
        '125x125',
        '120x600',
        '160x600',
        '180x150',
        '120x240',
        '200x200',
        '300x1050',
        '250x250',
        '320x50',
        '970x90',
        '970x250',
        '750x100',
        '750x200',
        '750x300',
    ];
    public $publisher_id;
    protected $fillable = [
        'short_headline',#@deprecated
        'name',
        'size',#@deprecated
        'width',
        'height',
    ];
    protected $visible = [
        'id',
        'short_headline',#@deprecated
        'name',
        'code',
        'size',#@deprecated
        'width',
        'height',
        'status',
    ];
    protected $appends = [
        'size',#@deprecated
        'short_headline',#@deprecated
        'code',
    ];
    protected $touches = ['site'];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function getCodeAttribute()
    {
        $replaceArr = [
            '{{publisherId}}' => $this->publisher_id ?? 0,
            '{{zoneId}}' => $this->id,
            '{{width}}' => $this->width,
            '{{height}}' => $this->height,
        ];

        return strtr(self::CODE_TEMPLATE, $replaceArr);
    }

    public function getShortHeadlineAttribute(): string
    {
        return $this->name;
    }

    public function setShortHeadlineAttribute($value): void
    {
        $this->name = $value;
    }

    public function setSizeAttribute(array $data): void
    {
        $oldSizeNumber = $data['size'] ?? false;
        if ($oldSizeNumber) {
            $size = Simulator::getZoneTypes()[$oldSizeNumber];
            $this->width = $size['width'];
            $this->height = $size['height'];
        }
    }

    public function getSizeAttribute(): array
    {
        return [
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
