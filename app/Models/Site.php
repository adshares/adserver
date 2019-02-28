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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property string uuid
 * @property int user_id
 * @property string name
 * @property array|null|string site_requires
 * @property array|null|string site_excludes
 * @property bool require_classified
 * @property bool exclude_unclassified
 * @property Zone[]|Collection zones
 * @method static Site create($input = null)
 * @method static get()
 * @mixin Builder
 */
class Site extends Model
{
    use Ownership;
    use SoftDeletes;
    use AutomateMutators;
    use BinHex;

    public const STATUS_DRAFT = 0;

    public const STATUS_INACTIVE = 1;

    public const STATUS_ACTIVE = 2;

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_INACTIVE, self::STATUS_ACTIVE];

    private const ZONE_STATUS = [
        Site::STATUS_DRAFT => Zone::STATUS_DRAFT,
        Site::STATUS_INACTIVE => Zone::STATUS_ARCHIVED,
        Site::STATUS_ACTIVE => Zone::STATUS_ACTIVE,
    ];

    public static $rules = [
        'name' => 'required|max:64',
        'primary_language' => 'required|max:2',
        'status' => 'required|numeric',
    ];

    protected $casts = [
        'site_requires' => 'json',
        'site_excludes' => 'json',
    ];

    protected $fillable = [
        'name',
        'status',
        'primary_language',
        'filtering',
    ];

    protected $hidden = [
        'deleted_at',
        'site_requires',
        'site_excludes',
        'zones',
    ];

    protected $appends = [
        'ad_units',
        'filtering',
        'code',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAdUnitsAttribute()
    {
        return $this->zones->map(
            function (Zone $zone) {
                $zone->publisher_id = $this->user_id;

                return $zone;
            }
        );
    }

    public function setFilteringAttribute(array $data): void
    {
        $this->site_requires = $data['requires'];
        $this->site_excludes = $data['excludes'];
        $this->require_classified = $data['require_classified'] ?? false;
        $this->exclude_unclassified = $data['exclude_unclassified'] ?? false;
    }

    public function getFilteringAttribute(): array
    {
        return [
            'requires' => $this->site_requires,
            'excludes' => $this->site_excludes,
            'require_classified' => (bool)$this->require_classified,
            'exclude_unclassified' => (bool)$this->exclude_unclassified,
        ];
    }

    public function getCodeAttribute(): string
    {
        $serverUrl = config('app.url');

        return "<script src=\"$serverUrl/supply/find.js\" async></script>";
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
        $this->zones->map(
            function (Zone $zone) use ($value) {
                $zone->status = Site::ZONE_STATUS[$value];
            }
        );
    }

    public static function fetchByPublicId(string $publicId): ?self
    {
        return self::where('uuid', hex2bin($publicId))->first();
    }
}
