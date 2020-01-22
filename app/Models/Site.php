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
use Adshares\Adserver\Services\Publisher\SiteCodeGenerator;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use function in_array;

/**
 * @property int id
 * @property string uuid
 * @property int user_id
 * @property string name
 * @property string domain
 * @property int status
 * @property array|null|string site_requires
 * @property array|null|string site_excludes
 * @property bool $require_classified deprecated
 * @property bool $exclude_unclassified deprecated
 * @property Zone[]|Collection zones
 * @property User user
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

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_INACTIVE,
        self::STATUS_ACTIVE,
    ];

    private const ZONE_STATUS = [
        Site::STATUS_DRAFT => Zone::STATUS_DRAFT,
        Site::STATUS_INACTIVE => Zone::STATUS_ARCHIVED,
        Site::STATUS_ACTIVE => Zone::STATUS_ACTIVE,
    ];

    public static $rules = [
        'name' => 'required|max:64',
        'domain' => 'required|regex:/^.+\..+$/|max:255',
        'primary_language' => 'required|max:2',
        'status' => 'required|numeric',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'site_requires' => 'json',
        'site_excludes' => 'json',
        'require_classified' => 'boolean',
        'exclude_unclassified' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'domain',
        'status',
        'primary_language',
        'filtering',
    ];

    protected $hidden = [
        'deleted_at',
        'site_requires',
        'site_excludes',
        'zones',
        'require_classified',
        'exclude_unclassified',
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
    }

    public function getFilteringAttribute(): array
    {
        return [
            'requires' => $this->site_requires,
            'excludes' => $this->site_excludes,
        ];
    }

    public function getCodeAttribute(): string
    {
        return SiteCodeGenerator::getCommonCode();
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

    public static function fetchById(int $id): ?self
    {
        return self::find($id);
    }

    public static function fetchByPublicId(string $publicId): ?self
    {
        return self::where('uuid', hex2bin($publicId))->first();
    }

    public function changeStatus(int $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: $status");
        }

        $this->status = $status;
    }
}
