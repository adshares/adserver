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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property DateTimeInterface created_at
 * @property DateTimeInterface updated_at
 * @property DateTimeInterface pay_time
 * @property int ads_payment_id
 * @property int network_campaign_id
 * @property int total_amount
 * @property int license_fee
 * @property int operator_fee
 * @property int paid_amount
 * @property float exchange_rate
 * @property int paid_amount_currency
 * @mixin Builder
 */
class NetworkBoostPayment extends Model
{
    use AutomateMutators;
    use BinHex;
    use HasFactory;

    protected $dates = [
        'pay_time',
    ];

    protected array $traitAutomate = [
        'campaign_public_id' => 'BinHex',
    ];

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

    public static function fetchPaymentsToExport(
        int $idFrom,
        int $limit,
        int $offset = 0,
    ): Collection {
        return self::query()
            ->select([
                'network_boost_payments.*',
                'ads_payments.address as payer',
                'network_campaigns.uuid AS campaign_public_id',
            ])
            ->where('network_boost_payments.id', '>=', $idFrom)
            ->join(
                'ads_payments',
                'network_boost_payments.ads_payment_id',
                '=',
                'ads_payments.id'
            )
            ->join(
                'network_campaigns',
                'network_boost_payments.network_campaign_id',
                '=',
                'network_campaigns.id'
            )
            ->take($limit)
            ->skip($offset)
            ->get();
    }

    public static function fetchOldest(DateTimeInterface $from): ?self
    {
        return NetworkBoostPayment::where('pay_time', '>=', $from)->first();
    }
}
