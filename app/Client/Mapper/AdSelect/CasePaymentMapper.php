<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Client\Mapper\AdSelect;

use Adshares\Adserver\Models\NetworkCasePayment;
use DateTime;

class CasePaymentMapper
{
    public static function map(NetworkCasePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'case_id' => $payment->network_case_id,
            'paid_amount' => $payment->total_amount,
            'pay_time' => $payment->pay_time->format(DateTime::ATOM),
            'transaction_id' => $payment->ads_payment_id,
        ];
    }
}
