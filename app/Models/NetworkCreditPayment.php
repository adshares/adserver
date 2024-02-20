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

namespace Adshares\Adserver\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkCreditPayment extends Model
{
    use HasFactory;

    public static function create(
        DateTimeInterface $payTime,
        int $adsPaymentId,
        int $networkCampaignId,
        int $totalAmount,
        int $licenseFee,
        int $operatorFee,
        int $paidAmount,
        float $exchangeRate,
        int $paidAmountCurrency,
    ): self {
        $p = new self();
        $p->pay_time = $payTime;
        $p->ads_payment_id = $adsPaymentId;
        $p->network_campaign_id = $networkCampaignId;
        $p->total_amount = $totalAmount;
        $p->license_fee = $licenseFee;
        $p->operator_fee = $operatorFee;
        $p->paid_amount = $paidAmount;
        $p->exchange_rate = $exchangeRate;
        $p->paid_amount_currency = $paidAmountCurrency;
        return $p;
    }
}
