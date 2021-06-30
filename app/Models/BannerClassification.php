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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Utilities\ArrayUtils;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property Banner banner
 * @property int banner_id
 * @property string classifier
 * @property array|null keywords
 * @property string|null signature
 * @property DateTime|null signed_at
 * @property int status
 * @method static Builder where(mixed $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static Builder join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
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
        'signed_at' => 'datetime:Y-m-d\TH:i:sP',
    ];

    protected $dates = [
        'signed_at',
    ];

    protected $traitAutomate = [
        'signature' => 'BinHex',
    ];

    protected $visible = [
        'keywords',
        'signature',
        'signed_at',
    ];

    public static function prepare(string $classifier): self
    {
        $bannerClassification = new self();
        $bannerClassification->classifier = $classifier;

        return $bannerClassification;
    }

    public function classified(array $keywords, string $signature, DateTime $signedAt): void
    {
        $this->keywords = $keywords;
        $this->signature = $signature;
        $this->signed_at = $signedAt;
        $this->status = BannerClassification::STATUS_SUCCESS;
        $this->save();
    }

    public function failed(): void
    {
        $this->status = BannerClassification::STATUS_FAILURE;
        $this->save();
    }

    public static function setStatusInProgress(array $bannerIds): void
    {
        BannerClassification::whereIn('banner_id', $bannerIds)->update(
            [
                'status' => BannerClassification::STATUS_IN_PROGRESS,
                'requested_at' => new DateTime(),
            ]
        );
    }

    public static function setStatusError(array $bannerIds): void
    {
        BannerClassification::whereIn('banner_id', $bannerIds)->update(
            [
                'status' => BannerClassification::STATUS_ERROR,
            ]
        );
    }

    public static function fetchByBannerIdAndClassifier(int $bannerId, string $classifier): ?self
    {
        /** @var BannerClassification $item */
        $item = BannerClassification::where('banner_id', $bannerId)->where('classifier', $classifier)->first();

        return $item;
    }

    public static function fetchClassifiedByBannerIds(array $bannerIds): Collection
    {
        /** @var Collection $grouped */
        $grouped = BannerClassification::whereIn('banner_id', $bannerIds)->where(
            'status',
            BannerClassification::STATUS_SUCCESS
        )->get(['banner_id', 'classifier', 'keywords', 'signature', 'signed_at'])->groupBy(['banner_id']);

        return $grouped->map(
            function ($item) {
                /** @var Collection $item */
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

    public static function fetchCampaignClassifications(int $campaignId): array
    {
        /** @var Collection $grouped */
        $grouped = BannerClassification::join(
            'banners',
            'banner_classifications.banner_id',
            '=',
            'banners.id'
        )->where('banners.campaign_id', $campaignId)->get(
            ['banner_id', 'classifier', 'keywords', 'banner_classifications.status']
        );

        $result = [];
        foreach ($grouped as $row) {
            /** @var BannerClassification $row */
            if (!array_key_exists($row->classifier, $result)) {
                $result[$row->classifier] = [
                    'classifier' => $row->classifier,
                    'status' => $row->status,
                    'keywords' => $row->keywords ?? [],
                ];
            } else {
                $result[$row->classifier]['keywords'] =
                    ArrayUtils::deepMerge($result[$row->classifier]['keywords'], $row->keywords ?? []);
                if (in_array($row->status, [self::STATUS_ERROR, self::STATUS_FAILURE])) {
                    $result[$row->classifier]['status'] = $row->status;
                } elseif (!in_array($result[$row->classifier]['status'], [self::STATUS_ERROR, self::STATUS_FAILURE])) {
                    $result[$row->classifier]['status'] = min($result[$row->classifier]['status'], $row->status);
                }
            }
        }

        return array_values($result);
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class, 'banner_id');
    }
}
