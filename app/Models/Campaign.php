<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

use Adshares\Adserver\Events\CampaignCreating;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\DateAtom;
use Adshares\Adserver\Models\Traits\Ownership;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Adserver\ViewModel\MetaverseVendor;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int id
 * @property string uuid
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property int user_id
 * @property string landing_url
 * @property string time_start
 * @property string|null time_end
 * @property int status
 * @property string name
 * @property array|null|string strategy_name
 * @property int budget
 * @property string medium
 * @property string|null $vendor
 * @property int max_cpc
 * @property int max_cpm
 * @property array|null|string targeting_requires
 * @property array|null|string targeting_excludes
 * @property Banner[]|Collection ads
 * @property Banner[]|Collection banners
 * @property Banner[]|Collection bannersWithContent
 * @property Collection conversions
 * @property User user
 * @property string secret
 * @property int conversion_click
 * @property string|null conversion_click_link
 * @property array bid_strategy
 * @property string bid_strategy_uuid
 * @property array basic_information
 * @property array|null classifications
 * @property array targeting
 * @property int $experiment_budget
 * @property Carbon|null $experiment_end_at
 * @mixin Builder
 */
class Campaign extends Model
{
    use Ownership;
    use SoftDeletes;
    use AutomateMutators;
    use BinHex;
    use DateAtom;
    use HasFactory;

    public const STATUS_DRAFT = 0;
    public const STATUS_INACTIVE = 1;
    public const STATUS_ACTIVE = 2;
    public const STATUS_SUSPENDED = 3;

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_INACTIVE, self::STATUS_ACTIVE, self::STATUS_SUSPENDED];

    public const CONVERSION_CLICK_NONE = 0;
    public const CONVERSION_CLICK_BASIC = 1;
    public const CONVERSION_CLICK_ADVANCED = 2;

    public const NAME_MAXIMAL_LENGTH = 255;
    public const URL_MAXIMAL_LENGTH = 1024;

    protected $dates = [
        'deleted_at',
        'time_start',
        'time_end',
        'experiment_end_at',
    ];

    protected $casts = [
        'created_at' => 'date:' . DateTimeInterface::ATOM,
        'updated_at' => 'date:' . DateTimeInterface::ATOM,
        'targeting_requires' => 'json',
        'targeting_excludes' => 'json',
        'status' => 'int',
        'budget' => 'int',
    ];

    protected $dispatchesEvents = [
        'creating' => CampaignCreating::class,
    ];

    protected $fillable = [
        'landing_url',
        'user_id',
        'name',
        'status',
        'budget',
        'medium',
        'vendor',
        'max_cpc',
        'max_cpm',
        'basic_information',
        'targeting_requires',
        'targeting_excludes',
        'time_start',
        'time_end',
        'conversion_click',
        'bid_strategy_uuid',
        'experiment_budget',
        'experiment_end_at',
    ];

    protected $visible = [
        'id',
        'uuid',
        'created_at',
        'updated_at',
        'classifications',
        'basic_information',
        'targeting',
        'ads',
        'conversions',
        'secret',
        'conversion_click',
        'conversion_click_link',
        'bid_strategy',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'time_start' => 'DateAtom',
        'time_end' => 'DateAtom',
        'bid_strategy_uuid' => 'BinHex',
        'experiment_end_at' => 'DateAtom',
    ];

    protected $appends = [
        'conversion_click_link',
        'basic_information',
        'targeting',
        'ads',
        'bid_strategy',
    ];

    public static function suspendAllForUserId(int $userId): int
    {
        return self::fetchByUserId($userId)->filter(
            function (self $campaign) {
                return $campaign->status === Campaign::STATUS_ACTIVE;
            }
        )->each(
            function (Campaign $campaign) {
                $campaign->status = Campaign::STATUS_SUSPENDED;
                $campaign->save();
            }
        )->count();
    }

    public static function deactivateAllForUserId(int $userId): int
    {
        return self::fetchByUserId($userId)->filter(
            function (Campaign $campaign) {
                return in_array($campaign->status, [Campaign::STATUS_ACTIVE, Campaign::STATUS_SUSPENDED], true);
            }
        )->each(
            function (Campaign $campaign) {
                $campaign->status = Campaign::STATUS_INACTIVE;
                $campaign->save();
                $campaign->banners->each(fn(Banner $banner) => $banner->deactivate());
            }
        )->count();
    }

    public static function isStatusAllowed(int $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function fetchAdvertiserId(int $campaignId): string
    {
        $campaign = self::find($campaignId);

        return $campaign->user->uuid;
    }

    public static function fetchByUserId(int $userId): Collection
    {
        return self::where('user_id', $userId)->get();
    }

    public static function fetchByUuid(string $uuid): ?self
    {
        return self::where('uuid', hex2bin($uuid))->first();
    }

    public static function fetchRequiredBudgetsPerUser(): Collection
    {
        return self::fetchActiveCampaigns(DateUtils::getDateTimeRoundedToNextHour())
            ->groupBy('user_id')
            ->map(
                static function (Collection $collection) {
                    return $collection->reduce(
                        static function (AdvertiserBudget $carry, Campaign $campaign) {
                            return $carry->add($campaign->advertiserBudget());
                        },
                        new AdvertiserBudget()
                    );
                }
            );
    }

    private static function fetchRequiredBudgetForAllCampaignsInCurrentPeriod(): AdvertiserBudget
    {
        return self::fetchActiveCampaigns(DateUtils::getDateTimeRoundedToCurrentHour())
            ->reduce(
                static function (AdvertiserBudget $carry, Campaign $campaign) {
                    return $carry->add($campaign->advertiserBudget());
                },
                new AdvertiserBudget(),
            );
    }

    public static function fetchActiveCampaigns(DateTimeInterface $dateTime): Collection
    {
        return self::where('status', self::STATUS_ACTIVE)
            ->where(
                fn($q) => $q->where('time_end', '>=', $dateTime)
                    ->orWhere('time_end', null)
            )
            ->get();
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class)->select(Banner::ALL_COLUMNS_EXCEPT_CONTENT);
    }

    public function bannersWithContent(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ConversionDefinition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAdsAttribute(): Collection
    {
        return $this->banners;
    }

    public function getBidStrategyAttribute(): array
    {
        $uuid = $this->bid_strategy_uuid;

        return [
            'name' => optional(BidStrategy::fetchByPublicId($uuid))->name,
            'uuid' => $uuid,
        ];
    }

    public function getConversionClickLinkAttribute(): ?string
    {
        switch ($this->conversion_click) {
            case self::CONVERSION_CLICK_BASIC:
                $params = [
                    'campaign_uuid' => $this->uuid,
                ];

                return (new SecureUrl(route('conversionClick.gif', $params)))->toString();
            case self::CONVERSION_CLICK_ADVANCED:
                $params = [
                    'campaign_uuid' => $this->uuid,
                    'value' => 'value',
                    'nonce' => 'nonce',
                    'ts' => 'timestamp',
                    'sig' => 'signature',
                ];

                return (new SecureUrl(route('conversionClick.gif', $params)))->toString();
            case self::CONVERSION_CLICK_NONE:
            default:
                return null;
        }
    }

    public function getTargetingAttribute(): array
    {
        return [
            "requires" => $this->targeting_requires,
            "excludes" => $this->targeting_excludes,
        ];
    }

    public function setBasicInformationAttribute(array $value): void
    {
        $this->status = $value["status"];
        $this->name = $value["name"];
        $this->landing_url = $value["target_url"];
        $this->max_cpc = $value["max_cpc"];
        $this->max_cpm = $value["max_cpm"];
        if ($value["budget"] < 0 || $value["experiment_budget"] < 0) {
            throw new InvalidArgumentException('Budget needs to be non-negative');
        }
        $this->budget = $value["budget"];
        $this->medium = $value["medium"];
        $this->vendor = $value["vendor"];
        $this->time_start = $value["date_start"];
        $this->time_end = $value["date_end"] ?? null;
        $this->experiment_budget = $value["experiment_budget"];
        $this->experiment_end_at = $value["experiment_end_at"] ?? null;
    }

    public function getBasicInformationAttribute(): array
    {
        return [
            "status" => $this->status,
            "name" => $this->name,
            "target_url" => $this->landing_url,
            "max_cpc" => $this->max_cpc,
            "max_cpm" => $this->max_cpm,
            "budget" => $this->budget,
            "medium" => $this->medium,
            "vendor" => $this->vendor,
            "date_start" => $this->time_start,
            "date_end" => $this->time_end,
            "experiment_budget" => $this->experiment_budget,
            "experiment_end_at" => $this->experiment_end_at,
        ];
    }

    public function changeStatus(int $status, ExchangeRate $exchangeRate): bool
    {
        if (!self::isStatusAllowed($status)) {
            return false;
        }

        if ($status === self::STATUS_ACTIVE && $this->isOutdated()) {
            return false;
        }

        if ($status === self::STATUS_ACTIVE && !$this->areBudgetLimitsMet()) {
            $status = self::STATUS_INACTIVE;
        }

        if ($status === $this->status) {
            return false;
        }

        $budget = self::fetchRequiredBudgetForAllCampaignsInCurrentPeriod();

        if ($status === self::STATUS_ACTIVE) {
            $budget = $budget->add($this->getBudgetForCurrentDateTime());
        }

        if (
            !$this->updateBlockadeOrFailIfNotAllowed(
                $exchangeRate->toClick($budget->total()),
                $exchangeRate->toClick($budget->bonusable())
            )
        ) {
            return false;
        }

        $this->status = $status;

        return true;
    }

    /**
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function checkBudgetLimits(): void
    {
        if ($this->budget < config('app.campaign_min_budget')) {
            throw new InvalidArgumentException(
                sprintf('Budget must be at least %d', config('app.campaign_min_budget'))
            );
        }

        $experimentBudget = $this->getEffectiveExperimentBudget();
        if (
            (
                0 !== $experimentBudget
                ||
                ($this->isCpa() && config('app.campaign_experiment_min_budget_for_cpa_required'))
            )
            && $experimentBudget < config('app.campaign_experiment_min_budget')
        ) {
            throw new InvalidArgumentException(
                sprintf('Experiment budget must be at least %d', config('app.campaign_experiment_min_budget'))
            );
        }

        if ($this->isAutoCpm() || $this->max_cpm >= config('app.campaign_min_cpm')) {
            return;
        }

        foreach ($this->conversions as $conversion) {
            /** @var $conversion ConversionDefinition */
            if ($conversion->value >= config('app.campaign_min_cpa')) {
                return;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'CPM must be at least %d or any CPC must be at least %d',
                config('app.campaign_min_cpm'),
                config('app.campaign_min_cpa'),
            )
        );
    }

    private function areBudgetLimitsMet(): bool
    {
        try {
            $this->checkBudgetLimits();
        } catch (InvalidArgumentException) {
            return false;
        }
        return true;
    }

    private function updateBlockadeOrFailIfNotAllowed(int $total, int $bonusable): bool
    {
        DB::beginTransaction();

        UserLedgerEntry::releaseBlockedAdExpense($this->user_id);

        try {
            UserLedgerEntry::blockAdExpense($this->user_id, $total, $bonusable);
        } catch (InvalidArgumentException $exception) {
            DB::rollBack();

            return false;
        }

        DB::commit();

        return true;
    }

    public static function updateBidStrategyUuid(string $newBidStrategyUuid, string $previousBidStrategyUuid): void
    {
        DB::update(
            'UPDATE campaigns SET bid_strategy_uuid=? WHERE bid_strategy_uuid=?',
            [hex2bin($newBidStrategyUuid), hex2bin($previousBidStrategyUuid)]
        );
    }

    public static function isBidStrategyUsed(string $bidStrategyUuid): bool
    {
        return null !== DB::selectOne(
            'SELECT 1 FROM campaigns WHERE bid_strategy_uuid=? AND deleted_at IS NULL LIMIT 1',
            [hex2bin($bidStrategyUuid)]
        );
    }

    private function getBudgetForCurrentDateTime(): AdvertiserBudget
    {
        if (
            $this->time_end !== null
            && DateTime::createFromFormat(DateTimeInterface::ATOM, $this->time_end)
            < DateUtils::getDateTimeRoundedToCurrentHour()
        ) {
            return new AdvertiserBudget();
        }

        return $this->advertiserBudget();
    }

    public function advertiserBudget(): AdvertiserBudget
    {
        $budget = $this->budget + $this->getEffectiveExperimentBudget();
        return new AdvertiserBudget($budget, $this->isDirectDeal() ? 0 : $budget);
    }

    private function isAutoCpm(): bool
    {
        return $this->max_cpm === null;
    }

    private function isCpa(): bool
    {
        return 0 === $this->max_cpm;
    }

    public function isDirectDeal(): bool
    {
        if (!isset($this->targeting_requires['site']['domain'])) {
            return false;
        }

        if (MediumName::Metaverse->value === $this->medium) {
            if (null !== ($vendor = MetaverseVendor::tryFrom($this->vendor))) {
                $domains = $this->targeting_requires['site']['domain'];
                return 1 !== count($domains) || $vendor->baseDomain() !== $domains[0];
            }
        }

        return true;
    }

    public function isOutdated(): bool
    {
        return $this->time_end !== null && DateTime::createFromFormat(DateTime::ATOM, $this->time_end) < new DateTime();
    }

    public function hasClickConversion(): bool
    {
        return in_array(
            $this->conversion_click,
            [Campaign::CONVERSION_CLICK_BASIC, Campaign::CONVERSION_CLICK_ADVANCED],
            true
        );
    }

    public function hasClickConversionAdvanced(): bool
    {
        return Campaign::CONVERSION_CLICK_ADVANCED === $this->conversion_click;
    }

    public function getEffectiveExperimentBudget(): int
    {
        return (null === $this->experiment_end_at || $this->experiment_end_at > Carbon::now())
            ? $this->experiment_budget
            : 0;
    }
}
