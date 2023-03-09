<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Adserver\Services\Publisher\SiteCodeGenerator;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Adserver\ViewModel\ZoneSize;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @property Site site
 * @property int id
 * @property string name
 * @property string code
 * @property string uuid
 * @property int site_id
 * @property string size
 * @property string[] scopes
 * @property string type
 * @property int status
 * @mixin Builder
 */
class Zone extends Model
{
    use SoftDeletes;
    use AutomateMutators;
    use BinHex;
    use HasFactory;

    public const DEFAULT_DEPTH = 0;
    public const DEFAULT_MINIMAL_DPI = 1;
    public const DEFAULT_NAME = 'Default';

    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_ARCHIVED = 2;

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    public const TYPE_DISPLAY = 'display';
    public const TYPE_MODEL = 'model';
    public const TYPE_POP = 'pop';

    public $publisher_id;

    protected $casts = [
        'scopes' => 'array',
    ];

    protected $fillable = [
        'name',
        'size',
        'scopes',
        'type',
        'status',
        'uuid',
    ];

    protected $visible = [
        'id',
        'name',
        'code',
        'size',
        'status',
        'type',
        'uuid'
    ];

    protected $appends = [
        'code',
    ];

    protected $touches = ['site'];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    public static function fetchOrCreate(
        int $siteId,
        ZoneSize $zoneSize,
        string $name,
        string $type = self::TYPE_DISPLAY,
    ): self {
        $size = $zoneSize->toString();
        $zone = self::where('site_id', $siteId)
            ->where('size', $size)
            ->where('name', $name)
            ->first();

        if (null === $zone) {
            if (null === ($site = Site::find($siteId))) {
                throw new InvalidArgumentException('Cannot find site');
            }

            $zone = new Zone();
            $zone->name = $name;
            $zone->site_id = $siteId;
            $zone->size = $size;

            if (MediumName::Web->value === $site->medium || self::TYPE_DISPLAY !== $type) {
                $scopes = [$size];
            } else {
                /** @var ConfigurationRepository $configurationRepository */
                $configurationRepository = resolve(ConfigurationRepository::class);
                $medium = $configurationRepository->fetchMedium($site->medium, $site->vendor);
                $scopes = Size::findBestFit($medium, $zoneSize);
                if (empty($scopes)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Cannot find placement matching width %d, height %d',
                            $zoneSize->getWidth(),
                            $zoneSize->getHeight(),
                        )
                    );
                }
            }
            $zone->scopes = $scopes;
            $zone->status = Zone::STATUS_ACTIVE;
            $zone->type = $type;
            $zone->save();
        }
        return $zone;
    }

    public static function fetchByPublicId(string $uuid): ?self
    {
        if (false === ($binId = @hex2bin($uuid))) {
            return null;
        }

        return Cache::remember(
            'zones.' . $uuid,
            config('app.network_data_cache_ttl'),
            function () use ($binId) {
                return self::where('uuid', $binId)->with(
                    [
                        'site',
                        'site.zones',
                        'site.user',
                    ]
                )->first();
            }
        );
    }

    public static function findByPublicIds(array $publicIds): Collection
    {
        $zones = [];
        foreach (array_unique($publicIds) as $uuid) {
            if (null !== ($zone = self::fetchByPublicId($uuid))) {
                $zones[] = $zone;
            }
        }
        return collect($zones);
    }

    public static function fetchPublisherPublicIdByPublicId(string $publicId): string
    {
        if (null === ($zone = self::fetchByPublicId($publicId))) {
            throw (new ModelNotFoundException())->setModel(static::class);
        }

        return $zone->site->user->uuid;
    }

    public static function fetchSitePublicIdByPublicId(string $publicId): string
    {
        if (null === ($zone = self::fetchByPublicId($publicId))) {
            throw (new ModelNotFoundException())->setModel(static::class);
        }

        return $zone->site->uuid;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function getCodeAttribute(): string
    {
        return SiteCodeGenerator::getZoneCode($this);
    }
}
