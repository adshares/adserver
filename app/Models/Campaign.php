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

use Adshares\Adserver\Events\CampaignCreating;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\DateAtom;
use Adshares\Adserver\Models\Traits\Ownership;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use InvalidArgumentException;

use function hex2bin;

/**
 * @property int id
 * @property string uuid
 * @property int created_at
 * @property int updated_at
 * @property int deleted_at
 * @property int user_id
 * @property string landing_url
 * @property string time_start
 * @property string|null time_end
 * @property int status
 * @property string name
 * @property array|null|string strategy_name
 * @property float bid
 * @property int budget
 * @property int max_cpc
 * @property int max_cpm
 * @property array|null|string targeting_requires
 * @property array|null|string targeting_excludes
 * @property Banner[]|Collection banners
 * @property Collection conversions
 * @property User user
 * @property string secret
 * @property int conversion_click
 * @property string bid_strategy_uuid
 * @property array basic_information
 * @property array classifications
 * @property array targeting
 * @method static Builder where(string $string, int $campaignId)
 * @mixin Builder
 */
class Campaign extends Model
{
    use Ownership;
    use SoftDeletes;
    use AutomateMutators;
    use BinHex;
    use DateAtom;

    public const STATUS_DRAFT = 0;

    public const STATUS_INACTIVE = 1;

    public const STATUS_ACTIVE = 2;

    public const STATUS_SUSPENDED = 3;

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_INACTIVE, self::STATUS_ACTIVE, self::STATUS_SUSPENDED];

    public const CONVERSION_CLICK_NONE = 0;

    public const CONVERSION_CLICK_BASIC = 1;

    public const CONVERSION_CLICK_ADVANCED = 2;

    public static $rules = [
//        'name' => 'required|max:255',
//        'landing_url' => 'required|max:1024',
//        'basic_information.budget' => 'required:numeric|min:1',
    ];

    protected $dates = [
        'deleted_at',
        'time_start',
        'time_end',
    ];

    protected $casts = [
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
        'require_count',
        'user_id',
        'name',
        'status',
        'budget',
        'max_cpc',
        'max_cpm',
        'basic_information',
        'targeting_requires',
        'targeting_excludes',
        'classification_status',
        'classification_tags',
        'conversion_click',
        'bid_strategy_uuid',
    ];

    protected $visible = [
        'id',
        'uuid',
        'created_at',
        'updated_at',
        'classifications',
        'classification_status',
        'classification_tags',
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
        $query = self::where('status', self::STATUS_ACTIVE)
            ->where(
                static function ($q) {
                    $dateTime = DateUtils::getDateTimeRoundedToNextHour();
                    $q->where('time_end', '>=', $dateTime)->orWhere('time_end', null);
                }
            );

        /** @var Collection $all */
        $all = $query->get();

        return $all->groupBy('user_id')
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
        $query = self::where('status', self::STATUS_ACTIVE)->where(
            static function ($q) {
                $dateTime = DateUtils::getDateTimeRoundedToCurrentHour();
                $q->where('time_end', '>=', $dateTime)->orWhere('time_end', null);
            }
        );

        $statics = $query->get();

        return $statics->reduce(
            static function (AdvertiserBudget $carry, Campaign $campaign) {
                return $carry->add($campaign->advertiserBudget());
            },
            new AdvertiserBudget()
        );
    }

    public function banners(): HasMany
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

    public function getAdsAttribute()
    {
        foreach ($this->banners as &$banner) {
            $banner['type'] = Banner::typeAsInteger($banner->creative_type);
        }

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
        if ($value["budget"] < 0) {
            throw new InvalidArgumentException('Budget needs to be non-negative');
        }
        $this->budget = $value["budget"];
        $this->time_start = $value["date_start"];
        $this->time_end = $value["date_end"] ?? null;
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
            "date_start" => $this->time_start,
            "date_end" => $this->time_end,
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

        if ($status === self::STATUS_ACTIVE && !$this->checkBudget()) {
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

    private function checkBudget(): bool
    {
        if ($this->budget < config('app.campaign_min_budget')) {
            return false;
        }

        if ($this->max_cpm >= config('app.campaign_min_cpm')) {
            return true;
        }

        foreach ($this->conversions as $conversion) {
            /** @var $conversion ConversionDefinition */
            if ($conversion->value >= config('app.campaign_min_cpa')) {
                return true;
            }
        }

        return false;
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
        return new AdvertiserBudget($this->budget, $this->isDirectDeal() ? 0 : $this->budget);
    }

    public function isDirectDeal(): bool
    {
        return isset($this->targeting_requires['site']['domain']);
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
}
