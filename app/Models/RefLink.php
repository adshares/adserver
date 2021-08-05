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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\DateAtom;
use Adshares\Adserver\Models\Traits\Ownership;
use Adshares\Adserver\Utilities\UuidStringGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property int user_id
 * @property User user
 * @property string token
 * @property ?string comment
 * @property ?string valid_until
 * @property bool single_use
 * @property bool used
 * @property int bonus
 * @property ?float refund
 * @property ?float kept_refund
 * @property ?string refund_valid_until
 * @property bool refund_active
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property ?Carbon deleted_at
 * @property string status
 * @method static RefLink create(array $input = [])
 */
class RefLink extends Model
{
    use AutomateMutators;
    use SoftDeletes;
    use Ownership;
    use DateAtom;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_OUTDATED = 'outdated';

    protected $attributes = [
        'single_use' => false,
        'used' => false,
        'kept_refund' => 1.0,
    ];

    protected $fillable = [
        'user_id',
        'token',
        'comment',
        'valid_until',
        'single_use',
        'bonus',
        'refund',
        'kept_refund',
        'refund_valid_until',
    ];

    public static array $rules = [
        'user_id' => 'required|numeric',
        'token' => 'required|min:6|max:64|unique:ref_links',
        'comment' => 'max:255',
        'valid_until' => 'date',
        'single_use' => 'boolean',
        'bonus' => 'numeric|min:0',
        'refund' => 'numeric|min:0|max:1',
        'kept_refund' => 'numeric|min:0|max:1',
        'refund_valid_until' => 'date',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'single_use' => 'boolean',
        'used' => 'boolean',
        'bonus' => 'integer',
        'refund' => 'float',
        'kept_refund' => 'float',
    ];

    protected $dates = [
        'valid_until',
        'refund_valid_until',
    ];

    protected $traitAutomate = [
        'valid_until' => 'DateAtom',
        'refund_valid_until' => 'DateAtom',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calculateRefund(int $amount): int
    {
        $refund = $this->refund ?? Config::fetchFloatOrFail(Config::REFERRAL_REFUND_COMMISSION);
        return (int)floor($amount * $refund) - $this->calculateBonus($amount);
    }

    public function calculateBonus(int $amount): int
    {
        $refund = $this->refund ?? Config::fetchFloatOrFail(Config::REFERRAL_REFUND_COMMISSION);
        return (int)round(floor($amount * $refund) * (1.0 - $this->kept_refund));
    }

    public function getStatusAttribute(): string
    {
        if (null !== $this->valid_until && (new Carbon($this->valid_until))->isBefore(now())) {
            return self::STATUS_OUTDATED;
        }

        if ($this->single_use && $this->used) {
            return self::STATUS_USED;
        }

        return self::STATUS_ACTIVE;
    }

    public function getRefundActiveAttribute(): bool
    {
        return null === $this->refund_valid_until || (new Carbon($this->refund_valid_until))->isAfter(now());
    }

    public static function fetchByUser(int $userId): Collection
    {
        return RefLink::select('*')
            ->selectSub(
                function (QueryBuilder $query) {
                    $query->from('users')
                        ->selectRaw('COUNT(*)')
                        ->whereRaw('users.ref_link_id = ref_links.id');
                },
                'usageCount'
            )
            ->selectSub(
                function (QueryBuilder $query) {
                    $query->from('user_ledger_entries')
                        ->selectRaw('IFNULL(SUM(amount), 0)')
                        ->whereRaw('user_ledger_entries.ref_link_id = ref_links.id')
                        ->where('status', UserLedgerEntry::STATUS_ACCEPTED)
                        ->where('type', UserLedgerEntry::TYPE_REFUND);
                },
                'refunded'
            )
            ->where('user_id', $userId)
            ->get();
    }

    public static function fetchByToken(string $token, bool $withInactive = false): ?self
    {
        $builder = RefLink::where('token', $token);
        if (!$withInactive) {
            $builder
                ->where(
                    function (Builder $query) {
                        $query->whereNull('valid_until')->orWhere('valid_until', '>=', Carbon::now());
                    }
                )
                ->where(
                    function (Builder $query) {
                        $query->where('single_use', false)->orWhere('used', false);
                    }
                );
        }
        return $builder->first();
    }

    public static function generateToken(): string
    {
        return Utils::urlSafeBase64Encode(hex2bin(UuidStringGenerator::v4()));
    }
}
