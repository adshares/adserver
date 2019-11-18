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
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use function array_map;
use function array_unique;
use function hex2bin;

/**
 * @property Site site
 * @property int id
 * @property string uuid
 * @property string size
 * @property array size_info
 * @property string label
 * @mixin Builder
 */
class Zone extends Model
{
    private const CODE_TEMPLATE = <<<HTML
<div class="{{selectorClass}}"
    data-zone="{{zoneId}}" 
    style="width:{{width}}px;height:{{height}}px;display: inline-block;margin: 0 auto"></div>
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

    public const SIZE_INFOS = [
        #best
        '300x250' => ['label' => 'medium-rectangle', 'tags' => ['Desktop', 'best']],
        '336x280' => ['label' => 'large-rectangle', 'tags' => ['Desktop', 'best']],
        '728x90' => ['label' => 'leaderboard', 'tags' => ['Desktop', 'best']],
        '300x600' => ['label' => 'half-page', 'tags' => ['Desktop', 'best']],
        '320x100' => ['label' => 'large-mobile-banner', 'tags' => ['Desktop', 'best', 'Mobile']],
        #other
        '320x50' => ['label' => 'mobile-banner', 'tags' => ['Desktop', 'Mobile']],
        '468x60' => ['label' => 'full-banner', 'tags' => ['Desktop']],
        '234x60' => ['label' => 'half-banner', 'tags' => ['Desktop']],
        '120x600' => ['label' => 'skyscraper', 'tags' => ['Desktop']],
        '120x240' => ['label' => 'vertical-banner', 'tags' => ['Desktop']],
        '160x600' => ['label' => 'wide-skyscraper', 'tags' => ['Desktop']],
        '300x1050' => ['label' => 'portrait', 'tags' => ['Desktop']],
        '970x90' => ['label' => 'large-leaderboard', 'tags' => ['Desktop']],
        '970x250' => ['label' => 'billboard', 'tags' => ['Desktop']],
        '250x250' => ['label' => 'square', 'tags' => ['Desktop']],
        '200x200' => ['label' => 'small-square', 'tags' => ['Desktop']],
        '180x150' => ['label' => 'small-rectangle', 'tags' => ['Desktop']],
        '125x125' => ['label' => 'button', 'tags' => ['Desktop']],
        #regional
        '240x400' => ['label' => 'vertical-rectangle', 'tags' => ['Desktop']],
        '980x120' => ['label' => 'panorama', 'tags' => ['Desktop']],
        '250x360' => ['label' => 'triple-widescreen', 'tags' => ['Desktop']],
        '930x180' => ['label' => 'top-banner', 'tags' => ['Desktop']],
        '580x400' => ['label' => 'netboard', 'tags' => ['Desktop']],
        #polish
        '750x100' => ['label' => 'single-billboard', 'tags' => ['Desktop', 'PL']],
        '750x200' => ['label' => 'double-billboard', 'tags' => ['Desktop', 'PL']],
        '750x300' => ['label' => 'triple-billboard', 'tags' => ['Desktop', 'PL']],
        # https://en.wikipedia.org/wiki/Web_banner
        '300x100' => ['label' => '3-to-1-rectangle', 'tags' => ['Desktop']],
        '120x90' => ['label' => 'button-one', 'tags' => ['Desktop']],
        '120x60' => ['label' => 'button-two', 'tags' => ['Desktop']],
        '88x31' => ['label' => 'micro-banner', 'tags' => ['Desktop']],
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
        'size_info',
        'status',
        'type',
        'uuid'
    ];

    protected $appends = [
        'size_info',
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
        return self::where('uuid', hex2bin($uuid))->first();
    }

    public static function findByPublicIds(array $publicIds): Collection
    {
        $binUniquePublicIds = array_unique(array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $publicIds
        ));

        return self::whereIn('uuid', $binUniquePublicIds)->get();
    }

    public static function fetchPublisherPublicIdByPublicId(string $publicId): string
    {
        $zone = self::where('uuid', hex2bin($publicId))->firstOrFail();
        $user = $zone->site->user;

        return $user->uuid;
    }

    public static function fetchSitePublicIdByPublicId(string $publicId): string
    {
        $zone = self::where('uuid', hex2bin($publicId))->firstOrFail();

        return $zone->site->uuid;
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function getCodeAttribute()
    {
        $size = Size::toDimensions($this->size);

        $replaceArr = [
            '{{zoneId}}' => $this->uuid,
            '{{width}}' => $size[0],
            '{{height}}' => $size[1],
            '{{selectorClass}}' => config('app.adserver_id'),
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

    public function getSizeInfoAttribute(): array
    {
        $sizeInfo = self::SIZE_INFOS[$this->size] ?? [];

        return [
            'label' => $sizeInfo['label'] ?? '',
            'tags' => $sizeInfo['tags'] ?? [],
        ];
    }
}
