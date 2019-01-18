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

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;

/**
 * @method static Builder where(string $string, int $userId)
 */
class UserLedgerEntry extends Model
{
    use SoftDeletes;

    public const STATUS_ACCEPTED = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_REJECTED = 2;

    public const TYPE_UNKNOWN = 0;

    public const TYPE_DEPOSIT = 1;

    public const TYPE_WITHDRAWAL = 2;

    public const TYPE_AD_INCOME = 3;

    public const TYPE_AD_EXPENDITURE = 4;

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'int',
    ];

    public static function getBalanceByUserId(int $userId): int
    {
        return self::balanceRelevantEntriesByUserId($userId)
            ->sum('amount');
    }

    public static function construct(int $userId, int $amount, int $status, int $type): self
    {
        $userLedgerEntry = new self();
        $userLedgerEntry->user_id = $userId;
        $userLedgerEntry->amount = $amount;
        $userLedgerEntry->status = $status;
        $userLedgerEntry->type = $type;

        return $userLedgerEntry;
    }

    public static function balanceRelevantEntriesByUserId(int $userId)
    {
        return self::where('user_id', $userId)
            ->where(function (EloquentBuilder $query) {
                $query->where('status', self::STATUS_ACCEPTED)
                    ->orWhere(function (EloquentBuilder $query) {
                        $query->where('status', self::STATUS_PENDING)
                            ->whereIn('type', [self::TYPE_AD_EXPENDITURE, self::TYPE_WITHDRAWAL]);
                    });
            });
    }

    public static function removeBlockade(): void
    {
        self::where('status', self::STATUS_PENDING)
            ->where('type', self::TYPE_AD_EXPENDITURE)
            ->delete();
    }
}
