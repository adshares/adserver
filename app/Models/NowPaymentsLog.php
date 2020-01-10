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

use Adshares\Adserver\Models\Traits\JsonValue;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property int user_id
 * @property string order_id
 * @property string status
 * @property float|null amount
 * @property int|null payment_id
 * @property DateTime created_at
 * @property array context
 * @mixin Builder
 */
final class NowPaymentsLog extends Model
{
    use JsonValue;

    const STATUS_INIT = 'init';

    const STATUS_WAITING = 'waiting';

    const STATUS_CONFIRMING = 'confirming';

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_SENDING = 'sending';

    const STATUS_PARTIALLY_PAID = 'partially-paid';

    const STATUS_FINISHED = 'finished';

    const STATUS_FAILED = 'failed';

    const STATUS_REFUNDED = 'refunded';

    const STATUS_EXPIRED = 'expired';

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
        float $amount = null,
        int $paymentId = null,
        array $context = []
    ): NowPaymentsLog {
        $log = new self();
        $log->user_id = $userId;
        $log->order_id = $orderId;
        $log->status = $status;
        $log->amount = $amount;
        $log->payment_id = $paymentId;
        $log->context = $context;

        return $log;
    }
}
