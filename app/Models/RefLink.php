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
use Adshares\Adserver\Models\Traits\Ownership;
use Adshares\Adserver\Utilities\UuidStringGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use \Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property int user_id
 * @property User user
 * @property string token
 * @property ?string comment
 * @property ?Carbon valid_until
 * @property bool single_use
 * @property bool used
 * @property int bonus
 * @property ?float refund
 * @property ?float kept_refund
 * @property ?Carbon refund_valid_until
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property ?Carbon deleted_at
 */
class RefLink extends Model
{
    use AutomateMutators;
    use SoftDeletes;
    use Ownership;

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
        'user_id' => 'required',
        'token' => 'required|min:6|max:64|unique:ref_links',
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

    public static function fetchAll(): Collection
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
                        ->selectRaw('SUM(amount)')
                        ->whereRaw('user_ledger_entries.ref_link_id = ref_links.id')
                        ->where('status', UserLedgerEntry::STATUS_ACCEPTED)
                        ->where('type', UserLedgerEntry::TYPE_REFUND);
                },
                'refunded'
            )
            ->get();
    }

    public static function fetchByToken(string $token): ?self
    {
        return RefLink::where('token', $token)
            ->where(
                function (Builder $query) {
                    $query->whereNull('valid_until')->orWhere('valid_until', '>=', Carbon::now());
                }
            )
            ->where(
                function (Builder $query) {
                    $query->where('single_use', false)->orWhere('used', false);
                }
            )->first();
    }

    public static function generateToken(): string
    {
        return Utils::urlSafeBase64Encode(hex2bin(UuidStringGenerator::v4()));
    }
}
