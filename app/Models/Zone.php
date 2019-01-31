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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Http\Controllers\Manager\Simulator;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use function array_map;
use function count;
use function GuzzleHttp\json_encode;
use function hex2bin;

/**
 * @property Site site
 * @property int id
 * @property string uuid
 */
class Zone extends Model
{
    private const CODE_TEMPLATE = <<<HTML
<div class="{{selectorClass}}"
    data-pub="{{publisherId}}" 
    data-zone="{{zoneId}}" 
    style="width:{{width}}px;height:{{height}}px;display: block;margin: 0 auto;background-color: #FAA"></div>
HTML;

    use SoftDeletes;
    use AutomateMutators;
    use BinHex;

    public const STATUS_DRAFT = 0;

    public const STATUS_ACTIVE = 1;

    public const STATUS_ARCHIVED = 2;

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    public const TYPE_IMAGE = 'image';

    public const TYPE_HTML = 'html';

    public const ZONE_TYPES = [
        self::TYPE_IMAGE,
        self::TYPE_HTML,
    ];

    public const ZONE_LABELS = [
        #best
        'medium-rectangle' => '300x250',
        'large-rectangle' => '336x280',
        'leaderboard' => '728x90',
        'half-page' => '300x600',
        'large-mobile-banner' => '320x100',
        #other
        'mobile-banner' => '320x50',
        'full-banner' => '468x60',
        'half-banner' => '234x60',
        'skyscraper' => '120x600',
        'vertical-banner' => '120x240',
        'wide-skyscraper' => '160x600',
        'portrait' => '300x1050',
        'large-leaderboard' => '970x90',
        'billboard' => '970x250',
        'square' => '250x250',
        'small-square' => '200x200',
        'small-rectangle' => '180x150',
        'button' => '125x125',
        #regional
        'vertical-rectangle' => '240x400',# Most popular size in Russia.
        'panorama' => '980x120', # Most popular size in Sweden and Finland. Can also be used as a substitute in Norway.
        'triple-widescreen' => '250x360', # Second most popular size in Sweden.
        'top-banner' => '930x180', # Very popular size in Denmark.
        'netboard' => '580x400', # Very popular size in Norway.
        #polish
        'single-billboard' => '750x100', # Very popular size in Poland.
        'double-billboard' => '750x200', # Most popular size in Poland.
        'triple-billboard' => '750x300', # Third most popular size in Poland.
        # https://en.wikipedia.org/wiki/Web_banner
        '3-to-1-rectangle' => '300x100',
        'button-one' => '120x90',
        'button-two' => '120x60',
        'micro-banner' => '88x31',
    ];

    public $publisher_id;

    protected $fillable = [
        'short_headline',#@deprecated
        'name',
        'size',
        'type',
        'status',
        'uuid',
    ];

    protected $visible = [
        'id',
        'short_headline',#@deprecated
        'name',
        'code',
        'size',
        'status',
        'type',
    ];

    protected $appends = [
        'size',
        'short_headline',#@deprecated
        'code',
    ];

    protected $touches = ['site'];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    public static function fetchByPublicId(string $uuid): ?Zone
    {
        return self::where('uuid', $uuid)->first();
    }

    public static function findByPublicIds(array $publicIds): Collection
    {
        $binPublicIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $publicIds
        );

        $zones = self::whereIn('uuid', $binPublicIds)->get();

        if (count($zones) !== count($binPublicIds)) {
            Log::warning(sprintf(
                'Missing zones. {"ids":%s,"zones":%s}',
                json_encode($publicIds),
                json_encode($zones->pluck(['id', 'width', 'height'])->toArray())
            ));
        }

        return $zones;
    }

    public static function fetchPublisherPublicIdByPublicId(string $publicId): string
    {
        $zone = self::where('uuid', hex2bin($publicId))->first();
        $user = $zone->site->user;

        return $user->uuid;
    }

    public static function fetchSitePublicIdByPublicId(string $publicId): string
    {
        $zone = self::where('uuid', hex2bin($publicId))->first();

        return $zone->site->uuid;
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function getCodeAttribute()
    {
        $replaceArr = [
            '{{publisherId}}' => $this->publisher_id ?? 0,
            '{{zoneId}}' => $this->uuid,
            '{{width}}' => $this->width,
            '{{height}}' => $this->height,
            '{{selectorClass}}' => config('app.website_banner_class'),
        ];

        return strtr(self::CODE_TEMPLATE, $replaceArr);
    }

    /** @deprecated */
    public function getShortHeadlineAttribute(): string
    {
        return $this->name;
    }

    /** @deprecated */
    public function setShortHeadlineAttribute($value): void
    {
        $this->name = $value;
    }

    public function getSizeAttribute(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'label' => $this->label,
            'tags' => collect(Simulator::getZoneTypes())->firstWhere('label', $this->label) ?? [],
        ];
    }

    public function setSizeAttribute(array $data): void
    {
        $label = $data['label'] ?? false;

        if ($label) {
            $sizeLabel = self::ZONE_LABELS[$label] ?? false;
            $this->attributes['label'] = $label;
            if ($sizeLabel) {
                $size = explode('x', $sizeLabel);
                $this->attributes['width'] = $size[0];
                $this->attributes['height'] = $size[1];
            }
        } else {
            $this->setWidth($data['width'] ?? false);
            $this->setHeight($data['height'] ?? false);
        }
    }

    private function setWidth($width): void
    {
        $this->attributes['width'] = $width;
        if ($this->attributes['width'] && ($this->attributes['height'] ?? false)) {
            $this->setSizeAttribute(
                [
                    'label' => Simulator::findLabelBySize("{$this->attributes['width']}x{$this->attributes['height']}"),
                ]
            );
        }
    }

    private function setHeight($height): void
    {
        $this->attributes['height'] = $height;
        if ($this->attributes['height'] && ($this->attributes['width'] ?? false)) {
            $this->setSizeAttribute(
                [
                    'label' => Simulator::findLabelBySize("{$this->attributes['width']}x{$this->attributes['height']}"),
                ]
            );
        }
    }
}
