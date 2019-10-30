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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string txid
 * @property string address
 * @property int amount
 * @property int status
 * @property int last_offset
 * @mixin Builder
 */
class AdsPayment extends Model
{
    public const STATUS_INVALID = -1;

    public const STATUS_NEW = 0;

    public const STATUS_USER_DEPOSIT = 1;

    public const STATUS_EVENT_PAYMENT = 2;

    public const STATUS_TRANSFER_FROM_COLD_WALLET = 3;

    public const STATUS_RESERVED = 64;

    protected $casts = [
        'amount' => 'int',
    ];

    public static function create(string $transactionId, int $amount, string $address): self
    {
        $adsPayment = new AdsPayment();
        $adsPayment->txid = $transactionId;
        $adsPayment->amount = $amount;
        $adsPayment->address = $address;

        return $adsPayment;
    }
}
