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
use function hex2bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use function count;
use function GuzzleHttp\json_encode;

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
        'leaderboard' => '728x90',
        'medium-rectangle' => '300x250',
        'large-rectangle' => '336x280',
        'half-page' => '300x600',
        'large-mobile-banner' => '320x100',
        #other
        'banner' => '468x60',
        'half-banner' => '234x60',
        'button' => '125x125',
        'skyscraper' => '120x600',
        'wide-skyscraper' => '160x600',
        'small-rectangle' => '180x150',
        'vertical-banner' => '120x240',
        'small-square' => '200x200',
        'portrait' => '300x1050',
        'square' => '250x250',
        'mobile-banner' => '320x50',
        'large-leaderboard' => '970x90',
        'billboard' => '970x250',
        #polish
        'single-billboard' => '750x100',
        'double-billboard' => '750x200',
        'triple-billboard' => '750x300',
    ];

    public const ZONE_SIZES = [
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

    public static function findByIds(array $zoneIdList): Collection
    {
        /** @var Collection $zones */

        foreach ($zoneIdList as &$item) {
            $item = hex2bin($item);
        }

        $zones = self::whereIn('uuid', $zoneIdList)->get();

        if (count($zones) !== count($zoneIdList)) {
            Log::warning(sprintf(
                'Missing zones. {"ids":%s,"zones":%s}',
                json_encode($zoneIdList),
                json_encode($zones->pluck(['id', 'width', 'height'])->toArray())
            ));
        }

        return $zones;
    }

    public static function fetchPublisherId(string $zoneId): string
    {
        $zone = self::where('uuid', hex2bin($zoneId))->first();
        $user = $zone->site->user;

        return $user->uuid;
    }

    public static function fetchSiteId(string $zoneId): string
    {
        $zone = self::where('uuid', hex2bin($zoneId))->first();
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
