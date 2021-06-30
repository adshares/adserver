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

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Models\Traits\TransactionId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @mixin Builder
 */
class NetworkPayment extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;
    use TransactionId;

    protected $fillable = [
        'receiver_address',
        'sender_address',
        'sender_host',
        'amount',
        'tx_id',
        'tx_time',
        'detailed_data_used',
        'processed',
        'ads_payment_id',
    ];

    protected $visible = [
        'receiver_address',
        'sender_address',
        'amount',
        'tx_id',
        'processed',
        'ads_payment_id',
    ];

    protected $traitAutomate = [
        'receiver_address' => 'AccountAddress',
        'sender_address' => 'AccountAddress',
        'tx_id' => 'TransactionId',
    ];

    public static function fetchNotProcessed(): Collection
    {
        return self::where('processed', '0')->get();
    }

    public static function registerNetworkPayment(
        string $receiverAddress,
        string $senderAddress,
        int $amount,
        AdsPayment $adsPayment
    ): NetworkPayment {
        return self::create(
            [
                'receiver_address' => $receiverAddress,
                'sender_address' => $senderAddress,
                'amount' => $amount,
                'ads_payment_id' => $adsPayment->id,
            ]
        );
    }
}
