<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Client\Mapper\AdSelect;

use Adshares\Adserver\Models\NetworkBoostPayment;
use DateTimeInterface;

class BoostPaymentMapper
{
    public static function map(NetworkBoostPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'campaign_id' => $payment->campaign_public_id,
            'paid_amount' => (int)((float)$payment->total_amount * $payment->exchange_rate),
            'pay_time' => $payment->pay_time->format(DateTimeInterface::ATOM),
            'payer' => $payment->payer,
        ];
    }
}
