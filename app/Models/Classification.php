<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Models;

use Adshares\Classify\Domain\Model\Classification as DomainClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id
 * @property int user_id
 * @property int site_id
 * @property int banner_id
 * @property string signature
 * @property int|null status
 */
class Classification extends Model
{
    protected $fillable = [
        'user_id',
        'site_id',
        'banner_id',
        'signature',
        'status',
    ];

    public static function fetchByBannerIds(array $ids)
    {
        return self::whereIn('banner_id', $ids)->get();
    }

    public static function classifyGlobal(int $userId, int $bannerId, bool $status, string $signature)
    {
        $classification = self::where('banner_id', $bannerId)
            ->where('user_id', $userId)
            ->first();

        if (!$classification) {
            $classification = new self();
            $classification->banner_id = $bannerId;
            $classification->user_id = $userId;
            $classification->site_id = null;
        }

        $classification->signature = $signature;
        $classification->status = $status;

        $classification->save();
    }

    public static function classifySite(int $siteId, int $userId, int $bannerId, bool $status, string $signature)
    {
        $classification = self::where('banner_id', $bannerId)
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->first();

        if (!$classification) {
            $classification = new self();
            $classification->banner_id = $bannerId;
            $classification->user_id = $userId;
            $classification->site_id = $siteId;
        }

        $classification->signature = $signature;
        $classification->status = $status;

        $classification->save();
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(NetworkBanner::class, 'banner_id');
    }
}
