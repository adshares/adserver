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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Request\Classifier\NetworkBannerFilter;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Supply\Domain\ValueObject\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use function array_map;
use function hex2bin;

/**
 * @property NetworkCampaign campaign
 * @mixin Builder
 */
class NetworkBanner extends Model
{
    public const TYPE_HTML = 'html';

    public const TYPE_IMAGE = 'image';

    public const ALLOWED_TYPES = [self::TYPE_IMAGE, self::TYPE_HTML];

    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'network_campaign_id',
        'source_created_at',
        'source_updated_at',
        'serve_url',
        'click_url',
        'view_url',
        'type',
        'checksum',
        'width',
        'height',
        'status',
        'classification',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id',
        'network_campaign_id',
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'checksum' => 'BinHex',
    ];

    protected $casts = [
        'classification' => 'json',
    ];

    public static function getTableName()
    {
        return with(new static())->getTable();
    }

    public static function findByUuid(string $bannerId): ?self
    {
        return self::where('uuid', hex2bin($bannerId))->first();
    }

    public static function fetch(int $limit, int $offset)
    {
        $query = self::queryBannersWithCampaign();

        return self::queryPaging($query, $limit, $offset)->get();
    }

    public static function fetchByFilter(NetworkBannerFilter $networkBannerFilter, int $limit, int $offset)
    {
        $query = self::queryByFilter($networkBannerFilter);

        return self::queryPaging($query, $limit, $offset)->get();
    }

    public static function fetchCountByFilter(NetworkBannerFilter $networkBannerFilter): int
    {
        $query = self::queryByFilter($networkBannerFilter);

        return $query->count();
    }

    private static function fetchAllByUserId(NetworkBannerFilter $networkBannerFilter): Builder
    {
        return self::queryBannersWithCampaign($networkBannerFilter);
    }

    private static function fetchApprovedByUserId(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        $query = self::queryBannersWithCampaign($networkBannerFilter);
        $query = self::queryJoinWithUserClassification($query, $userId, $siteId)
            ->where('classifications.status', Classification::STATUS_APPROVED);
        
        return $query;
    }

    public static function fetchRejectedByUserId(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        $query = self::queryBannersWithCampaign($networkBannerFilter);
        $query = self::queryJoinWithUserClassification($query, $userId, $siteId)
            ->where('classifications.status', Classification::STATUS_REJECTED);

        return $query;
    }

    public static function fetchUnclassifiedByUserId(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        $query = self::queryBannersWithCampaign($networkBannerFilter);
        $query->leftJoin(
            'classifications',
            function (JoinClause $join) use ($userId, $siteId) {
                $join->on('network_banners.id', '=', 'classifications.banner_id')
                    ->where(
                        [
                            'classifications.user_id' => $userId,
                            'classifications.site_id' => $siteId,
                        ]
                    );
            }
         )->whereNull('classifications.banner_id');

        return $query;
    }

    public static function fetchCount(): int
    {
        return self::where('network_banners.status', Status::STATUS_ACTIVE)->count();
    }

    private static function queryByFilter(NetworkBannerFilter $networkBannerFilter): Builder
    {
        if (!$networkBannerFilter->isApproved()
            && !$networkBannerFilter->isRejected()
            && !$networkBannerFilter->isUnclassified()) {
            $query = self::fetchAllByUserId($networkBannerFilter);
        } else {
            if ($networkBannerFilter->isApproved()) {
                $query = self::fetchApprovedByUserId($networkBannerFilter);
            } else {
                if ($networkBannerFilter->isRejected()) {
                    $query = self::fetchRejectedByUserId($networkBannerFilter);
                } else {
                    if ($networkBannerFilter->isUnclassified()) {
                        $query = self::fetchUnclassifiedByUserId($networkBannerFilter);
                    } else {
                        $query = self::fetchAllByUserId($networkBannerFilter);
                    }
                }
            }
        }

        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        if (null !== $siteId) {
            self::querySkipRejectedGlobally($query, $userId);
        }

        return $query;
    }

    private static function queryPaging(Builder $query, int $limit, int $offset): Builder
    {
        return $query->skip($offset)->take($limit);
    }

    private static function queryBannersWithCampaign(?NetworkBannerFilter $networkBannerFilter = null): Builder
    {
        $whereClause = [];
        $whereClause[] = ['network_banners.status', '=', Status::STATUS_ACTIVE];
        if (null !== $networkBannerFilter) {
            $type = $networkBannerFilter->getType();

            if (null !== $type) {
                $whereClause[] = ['network_banners.type', '=', $type];
            }
        }

        $query = self::where($whereClause)->orderBy(
            'network_banners.id',
            'desc'
        );

        if (null !== $networkBannerFilter) {
            $sizes = $networkBannerFilter->getSizes();

            if ($sizes) {
                $concatSizeExpression = DB::raw("CONCAT(`network_banners`.`width`, 'x', `network_banners`.`height`)");
                $query->whereIn($concatSizeExpression, $sizes);
            }
        }

        $query->join('network_campaigns', 'network_banners.network_campaign_id', '=', 'network_campaigns.id');
        $query->select(
            'network_banners.id',
            'network_banners.serve_url',
            'network_banners.type',
            'network_banners.width',
            'network_banners.height',
            'network_campaigns.source_host',
            'network_campaigns.budget',
            'network_campaigns.max_cpm',
            'network_campaigns.max_cpc'
        );

        return $query;
    }

    private static function queryJoinWithUserClassification(Builder $query, int $userId, ?int $siteId): Builder
    {
        $query = $query->join(
            'classifications',
            function (JoinClause $join) use ($userId, $siteId) {
                $join->on('network_banners.id', '=', 'classifications.banner_id')->where(
                    [
                        'classifications.user_id' => $userId,
                        'classifications.site_id' => $siteId,
                    ]
                );
            }
        );

        return $query;
    }

    private static function querySkipRejectedGlobally(Builder $query, int $userId): void
    {
        $query->leftJoin(
            'classifications as classification_global_reject',
            function (JoinClause $join) use ($userId) {
                $join->on('network_banners.id', '=', 'classification_global_reject.banner_id')->where(
                    [
                        'classification_global_reject.user_id' => $userId,
                        'classification_global_reject.site_id' => null,
                    ]
                );
            }
        )->where(
            function (Builder $whereClause) {
                $whereClause->where('classification_global_reject.status', Classification::STATUS_APPROVED)
                    ->orWhereNull('classification_global_reject.status');
            }
        );
    }

    public static function findIdsByUuids(array $publicUuids)
    {
        $binPublicIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $publicUuids
        );

        $banners = self::whereIn('uuid', $binPublicIds)
            ->select('id', 'uuid')
            ->get();

        $ids = [];

        foreach ($banners as $banner) {
            $ids[$banner->uuid] = $banner->id;
        }

        return $ids;
    }

    public function getAdSelectArray(): array
    {
        return [
            'banner_id' => $this->uuid,
            'banner_size' => $this->width.'x'.$this->height,
            'keywords' => [
                'type' => $this->type,
            ],
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NetworkCampaign::class, 'network_campaign_id');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Classification::class);
    }

    public static function fetchByPublicId(string $publicId): ?self
    {
        return self::where('uuid', hex2bin($publicId))->first();
    }
}
