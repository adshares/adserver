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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Http\Request\Classifier\NetworkBannerFilter;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Supply\Domain\ValueObject\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

use function array_map;
use function hex2bin;

/**
 * @property string click_url
 * @property string serve_url
 * @property string type
 * @property NetworkCampaign campaign
 * @mixin Builder
 */
class NetworkBanner extends Model
{
    use AutomateMutators;
    use BinHex;

    private const TYPE_HTML = 'html';

    private const TYPE_IMAGE = 'image';

    private const TYPE_DIRECT_LINK = 'direct';

    public const ALLOWED_TYPES = [
        self::TYPE_HTML,
        self::TYPE_IMAGE,
        self::TYPE_DIRECT_LINK,
    ];

    private const NETWORK_BANNERS_COLUMN_ID = 'network_banners.id';

    private const NETWORK_BANNERS_COLUMN_SERVE_URL = 'network_banners.serve_url';

    private const NETWORK_BANNERS_COLUMN_TYPE = 'network_banners.type';

    private const NETWORK_BANNERS_COLUMN_SIZE = 'network_banners.size';

    private const NETWORK_BANNERS_COLUMN_STATUS = 'network_banners.status';

    private const NETWORK_BANNERS_COLUMN_NETWORK_CAMPAIGN_ID = 'network_banners.network_campaign_id';

    private const NETWORK_BANNERS_COLUMN_CLASSIFICATION = 'network_banners.classification';

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
        'size',
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

    public static function fetchByPublicId(string $uuid): ?self
    {
        if (!Utils::isUuidValid($uuid)) {
            return null;
        }
        return Cache::remember(
            'network_banners.' . $uuid,
            (int)(config('app.network_data_cache_ttl') / 60),
            function () use ($uuid) {
                return self::where('uuid', hex2bin($uuid))->with(['campaign'])->first();
            }
        );
    }

    public static function fetch(int $limit, int $offset): Collection
    {
        $query = self::queryBannersWithCampaign();

        return self::queryPaging($query, $limit, $offset)->get();
    }

    public static function fetchByFilter(
        NetworkBannerFilter $networkBannerFilter,
        Collection $sites
    ): Collection {
        if ($sites->isEmpty()) {
            return new Collection();
        }
        return self::queryByFilter($networkBannerFilter)->get()->filter(
            function (NetworkBanner $banner) use ($sites) {
                foreach ($sites as $site) {
                    if ($site->matchFiltering($banner->classification ?? [])) {
                        return true;
                    }
                }
                return false;
            }
        );
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

        if (null !== $networkBannerFilter->getLandingUrl()) {
            $query->where('network_campaigns.landing_url', 'like', '%' . $networkBannerFilter->getLandingUrl() . '%');
        }

        return $query;
    }

    private static function getBaseQuery(NetworkBannerFilter $networkBannerFilter): Builder
    {
        if ($networkBannerFilter->isApproved()) {
            return self::fetchApproved($networkBannerFilter);
        }

        if ($networkBannerFilter->isRejected()) {
            return self::fetchRejected($networkBannerFilter);
        }

        if ($networkBannerFilter->isUnclassified()) {
            return self::fetchUnclassified($networkBannerFilter);
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
                $query->whereIn('network_banners.size', $sizes);
            }

            if (null !== ($networkBannerPublicId = $networkBannerFilter->getNetworkBannerPublicId())) {
                $query->where('network_banners.uuid', $networkBannerPublicId->bin());
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
            self::NETWORK_BANNERS_COLUMN_SIZE,
            self::NETWORK_BANNERS_COLUMN_CLASSIFICATION,
            self::NETWORK_CAMPAIGNS_COLUMN_LANDING_URL,
            self::NETWORK_CAMPAIGNS_COLUMN_SOURCE_HOST,
            self::NETWORK_CAMPAIGNS_COLUMN_BUDGET,
            self::NETWORK_CAMPAIGNS_COLUMN_MAX_CPM,
            self::NETWORK_CAMPAIGNS_COLUMN_MAX_CPC,
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

    public static function findSupplyIdsByDemandIds(array $demandIds, string $sourceAddress): array
    {
        $binDemandIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $demandIds
        );

        $banners =
            NetworkBanner::select(
                ['network_banners.uuid as uuid', 'network_banners.demand_banner_id as demand_banner_id']
            )
                ->join(
                    'network_campaigns',
                    function ($join) {
                        $join->on('network_banners.network_campaign_id', '=', 'network_campaigns.id');
                    }
                )
                ->where('network_campaigns.source_address', $sourceAddress)
                ->whereIn('network_banners.demand_banner_id', $binDemandIds)
                ->get();

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
}
