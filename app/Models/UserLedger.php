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

class UserLedger extends Model
{
    const STATUS_ACCEPTED = 0;
    const STATUS_PENDING = 1;
    const STATUS_REJECTED = 2;

    /**
     * Returns account balance of particular user.
     *
     * @param int $userId user id
     *
     * @return int balance
     */
    public static function getBalanceByUserId(int $userId): int
    {
        return self::where('user_id', $userId)->where('status', self::STATUS_ACCEPTED)->sum('amount');
    }
}
