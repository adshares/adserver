<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property string payment_id
 * @property Carbon payment_time
 * @property int|null amount
 * @property int status
 * @property string address
 * @property int last_offset
 * @mixin Builder
 */
class BridgePayment extends Model
{
    use HasFactory;

    public const STATUS_INVALID = -1;
    public const STATUS_NEW = 0;
    public const STATUS_RETRY = 1;
    public const STATUS_DONE = 2;

    public static function fetchByAddressAndPaymentIds(string $address, array $paymentIds): Collection
    {
        return (new self())->where('address', $address)->whereIn('payment_id', $paymentIds)->get();
    }

    public static function register(
        string $address,
        string $paymentId,
        DateTimeImmutable $paymentTime,
        ?int $amount,
        int $status,
    ): void {
        $bridgePayment = new self();
        $bridgePayment->address = $address;
        $bridgePayment->payment_id = $paymentId;
        $bridgePayment->payment_time = $paymentTime;
        $bridgePayment->amount = $amount;
        $bridgePayment->status = $status;
        $bridgePayment->saveOrFail();
    }
}
