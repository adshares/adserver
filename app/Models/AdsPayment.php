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

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string txid
 * @property string address
 * @property int amount
 * @property int status
 * @property DateTime created_at
 * @property DateTime updated_at
 * @property int last_offset
 * @property DateTime tx_time
 * @mixin Builder
 */
class AdsPayment extends Model
{
    public const STATUS_INVALID = -1;

    public const STATUS_NEW = 0;

    public const STATUS_USER_DEPOSIT = 1;

    public const STATUS_EVENT_PAYMENT = 2;

    public const STATUS_TRANSFER_FROM_COLD_WALLET = 3;

    public const STATUS_EVENT_PAYMENT_CANDIDATE = 4;

    public const STATUS_RESERVED = 64;

    protected $casts = [
        'amount' => 'int',
        'status' => 'int',
        'last_offset' => 'int',
    ];

    protected $dates = [
        'tx_time',
    ];

    public static function create(string $transactionId, int $amount, string $address): self
    {
        $adsPayment = new self();
        $adsPayment->txid = $transactionId;
        $adsPayment->amount = $amount;
        $adsPayment->address = $address;

        return $adsPayment;
    }

    public static function fetchByStatus(int $status): Collection
    {
        return self::where('status', $status)->get();
    }
}
