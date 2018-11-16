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

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $userId): \Illuminate\Database\Query\Builder;
 */
class UserLedgerEntry extends Model
{
    const STATUS_ACCEPTED = 0;
    const STATUS_PENDING = 1;
    const STATUS_REJECTED = 2;

    protected $casts = [
        'amount' => 'int',
    ];

    public static function getBalanceByUserId(int $userId): int
    {
        $sum = self::where('user_id', $userId)
            ->where('status', self::STATUS_ACCEPTED)
            ->sum('amount');

        return $sum;
    }
}
