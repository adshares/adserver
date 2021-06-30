<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

/**
 * @property int id
 * @property int user_id
 * @property int site_id
 * @property int banner_id
 * @property int|null status
 */
class Classification extends Model
{
    use Ownership;
    use AutomateMutators;

    public const STATUS_REJECTED = 0;

    public const STATUS_APPROVED = 1;

    protected $fillable = [
        'user_id',
        'site_id',
        'banner_id',
        'status',
    ];

    protected $casts = [
        'status' => 'bool',
    ];

    public static function fetchByBannerIds(array $ids)
    {
        return self::whereIn('banner_id', $ids)->get();
    }

    public static function classify(int $userId, int $bannerId, bool $status, ?int $siteId): void
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

        $classification->status = $status;

        DB::beginTransaction();

        try {
            $classification->save();

            if (null === $siteId && !$status) {
                self::where('banner_id', $bannerId)
                    ->where('user_id', $userId)
                    ->whereNotNull('site_id')
                    ->delete();
            }
        } catch (QueryException $queryException) {
            DB::rollBack();

            throw $queryException;
        }

        DB::commit();
    }

    public static function findByBannerId(int $bannerId)
    {
        return self::where('banner_id', $bannerId)->get();
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(NetworkBanner::class, 'banner_id');
    }
}
