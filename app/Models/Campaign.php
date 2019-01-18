<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\DateAtom;
use Adshares\Adserver\Models\Traits\Ownership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
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
 * @property \DateTime time_start
 * @property \DateTime time_end
 * @property int status
 * @property string name
 * @property array|null|string strategy_name
 * @property float bid
 * @property float budget
 * @property array|null|string targeting_requires
 * @property array|null|string targeting_excludes
 * @property Banner[]|Collection banners
 * @property User user
 * @method static Builder where(string $string, int $campaignId)
 * @method static Builder groupBy(string...$groups)
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
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'landing_url',
        'time_start',
        'time_end',
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
    ];

    protected $visible = [
        'id',
        'uuid',
        'created_at',
        'updated_at',
        'classification_status',
        'classification_tags',
        'basic_information',
        'targeting',
        'ads',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'time_start' => 'DateAtom',
        'time_end' => 'DateAtom',

    ];

    protected $appends = ['basic_information', 'targeting', 'ads'];

    public static function isStatusAllowed(int $status): bool
    {
        return in_array($status, self::STATUSES);
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

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAdsAttribute()
    {
        foreach ($this->banners as &$banner) {
            $size = $banner->creative_width.'x'.$banner->creative_height;
            $banner['type'] = $banner['creative_type'] === 'image' ? 0 : 1;
            $banner['size'] = array_search($size, Zone::ZONE_SIZES);
        }

        return $this->banners;
    }

    public function getTargetingAttribute()
    {
        return [
            "requires" => $this->targeting_requires,
            "excludes" => $this->targeting_excludes,
        ];
    }

    public function setBasicInformationAttribute(array $value)
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

    public function getBasicInformationAttribute()
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

    public function getBannersUrls(): array
    {
        $urls = [];

        foreach ($this->banners as $banner) {
            $urls[] = $banner->toArray()['serve_url'];
        }

        return $urls;
    }

    public function changeStatus(int $status): void
    {
        if ($status === $this->status) {
            return;
        }

        self::failIfInvalidStatus($status);
        $this->failIfTransitionNotAllowed($status);

        $this->status = $status;
    }

    private function failIfTransitionNotAllowed(int $status): void
    {
        if ($status === self::STATUS_ACTIVE) {
            $balance = UserLedgerEntry::getBalanceByUserId($this->user_id);

            $requiredBalance = self::fetchByUserId($this->user_id)
                ->filter(function (Campaign $campaign) {
                    return $campaign->status === Campaign::STATUS_ACTIVE || $campaign->id === $this->id;
                })->sum('budget');

            if ($balance < $requiredBalance) {
                throw new InvalidArgumentException('Campaign budgets exceed account balance');
            }
        }
    }

    private static function failIfInvalidStatus(int $value): void
    {
        if (!self::isStatusAllowed($value)) {
            throw new InvalidArgumentException(
                sprintf('Status must be one of [%s]', implode(',', self::STATUSES))
            );
        }
    }
}
