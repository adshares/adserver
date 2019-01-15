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

use Adshares\Adserver\Events\CreativeSha1;
use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use function hex2bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Campaign campaign
 */
class Banner extends Model
{
    public const IMAGE_TYPE = 0;

    public const HTML_TYPE = 1;

    public const STATUS_DRAFT = 0;

    public const STATUS_INACTIVE = 1;

    public const STATUS_ACTIVE = 2;

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_INACTIVE, self::STATUS_ACTIVE];

    use AutomateMutators;
    use BinHex;
    use SoftDeletes;

    protected $dates = [
        'deleted_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
        'saving' => CreativeSha1::class,
    ];

    protected $fillable = [
        'uuid',
        'campaign_id',
        'creative_contents',
        'creative_type',
        'creative_sha1',
        'creative_width',
        'creative_height',
        'name',
        'status',
    ];

    protected $hidden = [
        'creative_contents',
        'campaign_id',
        'deleted_at',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'creative_sha1' => 'BinHex',
    ];

    protected $touches = ['campaign'];

    public static function isStatusAllowed(int $status): bool
    {
        return in_array($status, self::STATUSES);
    }

    public static function type($type)
    {
        if ($type === self::IMAGE_TYPE) {
            return 'image';
        }

        return 'html';
    }

    public static function size($size)
    {
        if (!isset(Zone::ZONE_SIZES[$size])) {
            throw new \RuntimeException(sprintf('Wrong image size.'));
        }

        return Zone::ZONE_SIZES[$size];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    protected function toArrayExtras($array)
    {
        $array['serve_url'] = route('banner-serve', ['id' => $this->uuid]);
        $array['view_url'] = route('banner-view', ['id' => $this->uuid]);
        $array['click_url'] = route('banner-click', ['id' => $this->uuid]);

        if ($this->type === self::HTML_TYPE) {
            $array['html'] = $this->creative_contents;
        }

        if ($this->type === self::IMAGE_TYPE) {
            $array['image_url'] = $array['serve_url'];
        }

        return $array;
    }

    public static function fetchBanner(string $bannerUuid): ?Banner
    {
        $banner = self::where('uuid', hex2bin($bannerUuid))->first();

        if (!$banner) {
            return null;
        }

        return $banner;
    }
}
