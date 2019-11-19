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
 * @property string label
 * @property string type
 * @property array tags
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

    public $publisher_id;

    protected $fillable = [
        'name',
        'size',
        'type',
        'status',
        'uuid',
    ];

    protected $visible = [
        'id',
        'name',
        'code',
        'label',
        'size',
        'status',
        'tags',
        'type',
        'uuid'
    ];

    protected $appends = [
        'code',
        'label',
        'tags',
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

    public function getLabelAttribute(): string
    {
        return Size::SIZE_INFOS[$this->size]['label'] ?? '';
    }

    public function getTagsAttribute(): array
    {
        return Size::SIZE_INFOS[$this->size]['tags'] ?? [];
    }
}
