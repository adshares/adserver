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
use Adshares\Adserver\Mail\SiteApprovalPending;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\Ownership;
use Adshares\Adserver\Services\Publisher\SiteCodeGenerator;
use Adshares\Adserver\Services\Supply\SiteFilteringMatcher;
use Adshares\Adserver\Services\Supply\SiteFilteringUpdater;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SiteUtils;
use Adshares\Adserver\Utilities\SiteValidator;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Common\Application\Dto\PageRank;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * @property int id
 * @property string uuid
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property Carbon|null accepted_at
 * @property int user_id
 * @property string name
 * @property string domain
 * @property string url
 * @property float rank
 * @property string info
 * @property Carbon reassess_available_at
 * @property int status
 * @property string primary_language
 * @property string medium
 * @property string|null vendor
 * @property array filtering
 * @property array|null|string site_requires
 * @property array|null|string site_excludes
 * @property array|null categories
 * @property array|null categories_by_user
 * @property bool only_accepted_banners
 * @property string|null reject_reason
 * @property Zone[]|Collection zones
 * @property User user
 * @method static get()
 * @mixin Builder
 */
class Site extends Model
{
    use Ownership;
    use SoftDeletes;
    use AutomateMutators;
    use BinHex;
    use HasFactory;

    public const STATUS_DRAFT = 0;
    public const STATUS_INACTIVE = 1;
    public const STATUS_ACTIVE = 2;
    public const STATUS_PENDING_APPROVAL = 3;
    public const STATUS_REJECTED = 4;

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_INACTIVE,
        self::STATUS_ACTIVE,
    ];

    private const ZONE_STATUS = [
        Site::STATUS_DRAFT => Zone::STATUS_DRAFT,
        Site::STATUS_INACTIVE => Zone::STATUS_ARCHIVED,
        Site::STATUS_ACTIVE => Zone::STATUS_ACTIVE,
        Site::STATUS_PENDING_APPROVAL => Zone::STATUS_DRAFT,
        Site::STATUS_REJECTED => Zone::STATUS_ARCHIVED,
    ];

    private const ALL = '*';
    private const REJECT_REASON_ON_REJECTED_DOMAIN = 'Domain rejected';

    public static $rules = [
        'name' => 'required|max:64',
        'primary_language' => 'required|max:2',
        'status' => 'required|numeric',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'site_requires' => 'json',
        'site_excludes' => 'json',
        'only_accepted_banners' => 'boolean',
        'rank' => 'float',
        'categories' => 'json',
        'categories_by_user' => 'json',
    ];

    protected $fillable = [
        'name',
        'domain',
        'url',
        'status',
        'primary_language',
        'filtering',
        'only_accepted_banners',
    ];

    protected $hidden = [
        'deleted_at',
        'site_requires',
        'site_excludes',
        'zones',
        'rank',
        'info',
        'reassess_available_at',
        'categories',
        'categories_by_user',
    ];

    protected $appends = [
        'ad_units',
        'filtering',
        'code',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dates = [
        'reassess_available_at',
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

    public function matchFiltering(array $classification): bool
    {
        return SiteFilteringMatcher::checkClassification($this, $classification);
    }

    public function getCodeAttribute(): string
    {
        return SiteCodeGenerator::getCommonCode();
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
        $zoneStatus = Site::ZONE_STATUS[$value];
        $this->zones->map(
            function (Zone $zone) use ($zoneStatus) {
                $zone->status = $zoneStatus;
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

    public static function fetchSite(int $userId, string $domain): ?Site
    {
        return Site::where('user_id', $userId)
            ->where('domain', $domain)
            ->first();
    }

    public static function create(
        int $userId,
        string $url,
        string $name,
        string $medium,
        ?string $vendor,
        bool $onlyAcceptedBanners,
        int $status = Site::STATUS_ACTIVE,
        string $primaryLanguage = 'en',
        array $categoriesByUser = null,
        array $filtering = null
    ): Site {
        if ($categoriesByUser === null) {
            $categoriesByUser = ['unknown'];
        }
        if ($filtering === null) {
            $filtering = [
                'requires' => [],
                'excludes' => [],
            ];
        }

        $site = new Site();
        $site->categories_by_user = $categoriesByUser;
        $site->domain = DomainReader::domain($url);
        $site->filtering = $filtering;
        $site->medium = $medium;
        $site->vendor = $vendor;
        $site->name = $name;
        $site->only_accepted_banners = $onlyAcceptedBanners;
        $site->primary_language = $primaryLanguage;
        $site->url = $url;
        $site->user_id = $userId;
        if (Site::STATUS_ACTIVE === $status) {
            $site->approvalProcedure();
        } else {
            $site->status = $status;
        }
        $site->save();

        resolve(SiteFilteringUpdater::class)->addClassificationToFiltering($site);

        return $site;
    }

    public static function fetchOrCreate(
        int $userId,
        string $url,
        string $medium,
        ?string $vendor,
    ): self {
        $domain = DomainReader::domain($url);
        $site = self::fetchSite($userId, $domain);

        if (null === $site) {
            $name = $domain;
            $url = rtrim($url, '/');
            if (MediumName::Metaverse->value === $medium) {
                if ('decentraland' === $vendor) {
                    if (!SiteUtils::isValidDecentralandUrl($url)) {
                        throw new InvalidArgumentException('Invalid Decentraland URL');
                    }
                    $name = SiteUtils::extractNameFromDecentralandDomain($domain);
                } elseif ('cryptovoxels' === $vendor) {
                    if (!SiteUtils::isValidCryptovoxelsUrl($url)) {
                        throw new InvalidArgumentException('Invalid Cryptovoxels URL');
                    }
                    $name = SiteUtils::extractNameFromCryptovoxelsDomain($domain);
                }
            }

            if (!SiteValidator::isUrlValid($url)) {
                throw new InvalidArgumentException('Invalid URL');
            }
            resolve(ConfigurationRepository::class)->fetchMedium($medium, $vendor);

            $onlyAcceptedBanners =
                Config::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT
                !== config('app.site_classifier_local_banners');
            $filtering = [
                'requires' => config('app.site_filtering_require_on_auto_create'),
                'excludes' => config('app.site_filtering_exclude_on_auto_create'),
            ];
            $site = Site::create($userId, $url, $name, $medium, $vendor, $onlyAcceptedBanners, filtering: $filtering);
        } else {
            if ($site->medium !== $medium || $site->vendor !== $vendor) {
                throw new InvalidArgumentException('Site exists for another vendor');
            }
        }

        return $site;
    }

    public static function fetchAll(int $previousChunkLastId = 0, int $limit = PHP_INT_MAX): Collection
    {
        return self::getSitesChunkBuilder($previousChunkLastId, $limit)->get();
    }

    public static function fetchInVerification(int $previousChunkLastId = 0, int $limit = PHP_INT_MAX): Collection
    {
        return self::getSitesChunkBuilder($previousChunkLastId, $limit)
            ->where('info', AdUser::PAGE_INFO_UNKNOWN)
            ->get();
    }

    public static function rejectByDomains(array $domains): void
    {
        foreach ($domains as $domain) {
            self::whereNot('status', self::STATUS_REJECTED)
                ->where(function (Builder $sub) use ($domain) {
                    $sub->where('domain', 'like', '%.' . $domain)->orWhere('domain', $domain);
                })
                ->update([
                    'reject_reason' => self::REJECT_REASON_ON_REJECTED_DOMAIN,
                    'status' => self::STATUS_REJECTED,
                ]);
        }
    }

    private static function getSitesChunkBuilder(int $previousChunkLastId, int $limit): Builder
    {
        return self::where('id', '>', $previousChunkLastId)->limit($limit);
    }

    public function changeStatus(int $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: $status");
        }

        $this->status = $status;
    }

    public function updateWithPageRank(PageRank $pageRank): void
    {
        $this->rank = $pageRank->getRank();
        $this->info = $pageRank->getInfo();
        $this->save();
    }

    public function updateCategories(array $categories): void
    {
        $this->categories = $categories;
        $this->save();
    }

    public function approvalProcedure(): void
    {
        if (null !== $this->accepted_at) {
            $this->status = self::STATUS_ACTIVE;
            return;
        }
        if (SitesRejectedDomain::isDomainRejected($this->domain)) {
            $this->status = self::STATUS_REJECTED;
            $this->reject_reason = self::REJECT_REASON_ON_REJECTED_DOMAIN;
            return;
        }
        if (self::isApprovalRequired($this->medium)) {
            $this->status = self::STATUS_PENDING_APPROVAL;
            Mail::to(config('app.support_email'))
                ->queue(new SiteApprovalPending($this->user_id, $this->url));
            return;
        }
        $this->status = self::STATUS_ACTIVE;
        $this->accepted_at = new DateTimeImmutable();
    }

    private static function isApprovalRequired(string $medium): bool
    {
        $mediumList = config('app.site_approval_required');
        return in_array($medium, $mediumList) || in_array(self::ALL, $mediumList);
    }
}
