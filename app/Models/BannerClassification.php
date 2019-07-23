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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property Banner banner
 * @property int banner_id
 * @property string classifier
 * @property array|null keywords
 * @property string signature
 * @property int status
 */
class BannerClassification extends Model
{
    use AutomateMutators;
    use BinHex;

    public const STATUS_NEW = 0;

    public const STATUS_IN_PROGRESS = 1;

    public const STATUS_ERROR = 2;

    public const STATUS_SUCCESS = 3;

    public const STATUS_FAILURE = 4;

    protected $casts = [
        'keywords' => 'array',
    ];

    protected $traitAutomate = [
        'signature' => 'BinHex',
    ];

    protected $visible = [
        'keywords',
        'signature',
    ];

    public static function prepare(string $classifier): self
    {
        $bannerClassification = new self();
        $bannerClassification->classifier = $classifier;

        return $bannerClassification;
    }

    public static function fetchByBannerIdAndClassifier(int $bannerId, string $classifier): ?self
    {
        return BannerClassification::where('banner_id', $bannerId)->where('classifier', $classifier)->first();
    }

    public static function fetchClassifiedByBannerIds(array $bannerIds): Collection
    {
        /** @var Collection $grouped */
        $grouped = BannerClassification::whereIn('banner_id', $bannerIds)->where(
            'status',
            BannerClassification::STATUS_SUCCESS
        )->get(['banner_id', 'classifier', 'keywords', 'signature'])->groupBy(['banner_id']);

        return $grouped->map(
            function ($item) {
                return $item->keyBy('classifier');
            }
        );
    }

    public static function fetchPendingForClassification(): Collection
    {
        return BannerClassification::whereIn(
            'status',
            [BannerClassification::STATUS_NEW, BannerClassification::STATUS_ERROR]
        )->get();
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class, 'banner_id');
    }
}
