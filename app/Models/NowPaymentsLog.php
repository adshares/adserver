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

use Adshares\Adserver\Models\Traits\JsonValue;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property int user_id
 * @property string order_id
 * @property string status
 * @property float amount
 * @property string currency
 * @property string|null payment_id
 * @property DateTime created_at
 * @property array context
 * @mixin Builder
 */
final class NowPaymentsLog extends Model
{
    use JsonValue;

    public const STATUS_INIT = 'init';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_CONFIRMING = 'confirming';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SENDING = 'sending';

    public const STATUS_PARTIALLY_PAID = 'partially-paid';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DEPOSIT_INIT = 'deposit-init';

    public const STATUS_DEPOSIT = 'deposit';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'payment_id' => 'integer',
        'context' => 'json',
    ];

    protected $dates = [
        'created_at',
    ];

    public static function create(
        int $userId,
        string $orderId,
        string $status,
        float $amount,
        string $currency,
        ?string $paymentId = null,
        array $context = []
    ): NowPaymentsLog {
        $log = new self();
        $log->user_id = $userId;
        $log->order_id = $orderId;
        $log->status = $status;
        $log->amount = $amount;
        $log->currency = $currency;
        $log->payment_id = $paymentId;
        $log->context = $context;

        return $log;
    }
}
