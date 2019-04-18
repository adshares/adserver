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
use Illuminate\Database\Eloquent\Collection;
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

    private const NETWORK_BANNERS_COLUMN_ID = 'network_banners.id';

    private const NETWORK_BANNERS_COLUMN_SERVE_URL = 'network_banners.serve_url';

    private const NETWORK_BANNERS_COLUMN_TYPE = 'network_banners.type';

    private const NETWORK_BANNERS_COLUMN_WIDTH = 'network_banners.width';

    private const NETWORK_BANNERS_COLUMN_HEIGHT = 'network_banners.height';

    private const NETWORK_BANNERS_COLUMN_STATUS = 'network_banners.status';

    private const NETWORK_BANNERS_COLUMN_NETWORK_CAMPAIGN_ID = 'network_banners.network_campaign_id';

    private const CLASSIFICATIONS_COLUMN_BANNER_ID = 'classifications.banner_id';

    private const CLASSIFICATIONS_COLUMN_STATUS = 'classifications.status';

    private const CLASSIFICATIONS_COLUMN_SITE_ID = 'classifications.site_id';

    private const CLASSIFICATIONS_COLUMN_USER_ID = 'classifications.user_id';

    private const NETWORK_CAMPAIGNS_COLUMN_ID = 'network_campaigns.id';

    private const NETWORK_CAMPAIGNS_COLUMN_LANDING_URL = 'network_campaigns.landing_url';

    private const NETWORK_CAMPAIGNS_COLUMN_SOURCE_HOST = 'network_campaigns.source_host';

    private const NETWORK_CAMPAIGNS_COLUMN_BUDGET = 'network_campaigns.budget';

    private const NETWORK_CAMPAIGNS_COLUMN_MAX_CPM = 'network_campaigns.max_cpm';

    private const NETWORK_CAMPAIGNS_COLUMN_MAX_CPC = 'network_campaigns.max_cpc';

    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'demand_banner_id',
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
        'demand_banner_id' => 'BinHex',
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

    public static function fetch(int $limit, int $offset): Collection
    {
        $query = self::queryBannersWithCampaign();

        return self::queryPaging($query, $limit, $offset)->get();
    }

    public static function fetchByFilter(NetworkBannerFilter $networkBannerFilter, int $limit, int $offset): Collection
    {
        $query = self::queryByFilter($networkBannerFilter);


        return self::queryPaging($query, $limit, $offset)->get();
    }

    public static function fetchCountByFilter(NetworkBannerFilter $networkBannerFilter): int
    {
        $query = self::queryByFilter($networkBannerFilter);

        return $query->count();
    }

    private static function fetchAll(NetworkBannerFilter $networkBannerFilter): Builder
    {
        return self::queryBannersWithCampaign($networkBannerFilter);
    }

    private static function fetchApproved(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        $query = self::queryBannersWithCampaign($networkBannerFilter);

        return self::queryJoinWithUserClassification($query, $userId, $siteId)->where(
            self::CLASSIFICATIONS_COLUMN_STATUS,
            Classification::STATUS_APPROVED
        );
    }

    public static function fetchRejected(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        $query = self::queryBannersWithCampaign($networkBannerFilter);

        return self::queryJoinWithUserClassification($query, $userId, $siteId)->where(
            self::CLASSIFICATIONS_COLUMN_STATUS,
            Classification::STATUS_REJECTED
        );
    }

    public static function fetchUnclassified(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $userId = $networkBannerFilter->getUserId();
        $siteId = $networkBannerFilter->getSiteId();

        $query = self::queryBannersWithCampaign($networkBannerFilter);
        $query->leftJoin(
            'classifications',
            function (JoinClause $join) use ($userId, $siteId) {
                $join->on(self::NETWORK_BANNERS_COLUMN_ID, '=', self::CLASSIFICATIONS_COLUMN_BANNER_ID)->where(
                    [
                        self::CLASSIFICATIONS_COLUMN_USER_ID => $userId,
                        self::CLASSIFICATIONS_COLUMN_SITE_ID => $siteId,
                    ]
                );
            }
        )->whereNull(self::CLASSIFICATIONS_COLUMN_BANNER_ID);

        return $query;
    }

    public static function fetchCount(): int
    {
        return self::where(self::NETWORK_BANNERS_COLUMN_STATUS, Status::STATUS_ACTIVE)->count();
    }

    private static function queryByFilter(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $query = self::getBaseQuery($networkBannerFilter);

        if (null !== $networkBannerFilter->getSiteId()) {
            $userId = $networkBannerFilter->getUserId();
            $query = self::querySkipRejectedGlobally($query, $userId);
        }

        return $query;
    }

    private static function getBaseQuery(NetworkBannerFilter $networkBannerFilter): Builder
    {
        $query = null;

        if ($networkBannerFilter->isApproved()) {
            $query = self::fetchApproved($networkBannerFilter);
        }

        if ($networkBannerFilter->isRejected()) {
            $query = self::fetchRejected($networkBannerFilter);
        }

        if ($networkBannerFilter->isUnclassified()) {
            $query = self::fetchUnclassified($networkBannerFilter);
        }

        if ($query) {
            return $query;
        }

        return self::fetchAll($networkBannerFilter);
    }

    private static function queryPaging(Builder $query, int $limit, int $offset): Builder
    {
        return $query->skip($offset)->take($limit);
    }

    private static function queryBannersWithCampaign(?NetworkBannerFilter $networkBannerFilter = null): Builder
    {
        $whereClause = [];
        $whereClause[] = [self::NETWORK_BANNERS_COLUMN_STATUS, '=', Status::STATUS_ACTIVE];
        if (null !== $networkBannerFilter) {
            $type = $networkBannerFilter->getType();

            if (null !== $type) {
                $whereClause[] = [self::NETWORK_BANNERS_COLUMN_TYPE, '=', $type];
            }
        }

        $query = self::where($whereClause)->orderBy(
            self::NETWORK_BANNERS_COLUMN_ID,
            'desc'
        );

        if (null !== $networkBannerFilter) {
            $sizes = $networkBannerFilter->getSizes();

            if ($sizes) {
                $concatSizeExpression = DB::raw("CONCAT(`network_banners`.`width`, 'x', `network_banners`.`height`)");
                $query->whereIn($concatSizeExpression, $sizes);
            }
        }

        $query->join(
            'network_campaigns',
            self::NETWORK_BANNERS_COLUMN_NETWORK_CAMPAIGN_ID,
            '=',
            self::NETWORK_CAMPAIGNS_COLUMN_ID
        );
        $query->select(
            self::NETWORK_BANNERS_COLUMN_ID,
            self::NETWORK_BANNERS_COLUMN_SERVE_URL,
            self::NETWORK_BANNERS_COLUMN_TYPE,
            self::NETWORK_BANNERS_COLUMN_WIDTH,
            self::NETWORK_BANNERS_COLUMN_HEIGHT,
            self::NETWORK_CAMPAIGNS_COLUMN_LANDING_URL,
            self::NETWORK_CAMPAIGNS_COLUMN_SOURCE_HOST,
            self::NETWORK_CAMPAIGNS_COLUMN_BUDGET,
            self::NETWORK_CAMPAIGNS_COLUMN_MAX_CPM,
            self::NETWORK_CAMPAIGNS_COLUMN_MAX_CPC
        );

        return $query;
    }

    private static function queryJoinWithUserClassification(Builder $query, int $userId, ?int $siteId): Builder
    {
        return $query->join(
            'classifications',
            function (JoinClause $join) use ($userId, $siteId) {
                $join->on(self::NETWORK_BANNERS_COLUMN_ID, '=', self::CLASSIFICATIONS_COLUMN_BANNER_ID)->where(
                    [
                        self::CLASSIFICATIONS_COLUMN_USER_ID => $userId,
                        self::CLASSIFICATIONS_COLUMN_SITE_ID => $siteId,
                    ]
                );
            }
        );
    }

    private static function querySkipRejectedGlobally(Builder $query, int $userId): Builder
    {
        return $query->leftJoin(
            'classifications as classification_global_reject',
            function (JoinClause $join) use ($userId) {
                $join->on(self::NETWORK_BANNERS_COLUMN_ID, '=', 'classification_global_reject.banner_id')->where(
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

    public static function findIdsByUuids(array $publicUuids): array
    {
        $binPublicIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $publicUuids
        );

        $banners = self::whereIn('uuid', $binPublicIds)->select('id', 'uuid')->get();

        $ids = [];

        foreach ($banners as $banner) {
            $ids[$banner->uuid] = $banner->id;
        }

        return $ids;
    }

    public static function findPublicIdsByDemandIds(array $demandIds): array
    {
        $binDemandIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $demandIds
        );

        $banners = self::whereIn('demand_banner_id', $binDemandIds)->select('uuid', 'demand_banner_id')->get();

        $ids = [];

        foreach ($banners as $banner) {
            $ids[$banner->demand_banner_id] = $banner->uuid;
        }

        return $ids;
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
