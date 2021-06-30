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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int id
 * @property int network_case_id
 * @property Carbon created_at
 * @property Carbon pay_time
 * @property int ads_payment_id
 * @property int total_amount
 * @property int license_fee
 * @property int operator_fee
 * @property int paid_amount
 * @property string exchange_rate
 * @property int paid_amount_currency
 * @mixin Builder
 */
class NetworkCasePayment extends Model
{
    use AutomateMutators;
    use BinHex;

    public $timestamps = false;

    /** @var array */
    protected $dates = [
        'created_at',
        'pay_time',
    ];

    /** @var array */
    protected $fillable = [
        'pay_time',
        'ads_payment_id',
        'total_amount',
        'license_fee',
        'operator_fee',
        'paid_amount',
        'exchange_rate',
        'paid_amount_currency',
    ];

    /** @var array */
    protected $visible = [];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
        /** Mutators from @see NetworkCase::class */
        'case_id' => 'BinHex',
        'publisher_id' => 'BinHex',
        'site_id' => 'BinHex',
        'zone_id' => 'BinHex',
        'campaign_id' => 'BinHex',
        'banner_id' => 'BinHex',
    ];

    public static function create(
        DateTime $payTime,
        int $adsPaymentId,
        int $totalAmount,
        int $licenseFee,
        int $operatorFee,
        int $paidAmount,
        float $exchangeRate,
        int $paidAmountCurrency
    ): self {
        return new self(
            [
                'pay_time' => $payTime,
                'ads_payment_id' => $adsPaymentId,
                'total_amount' => $totalAmount,
                'license_fee' => $licenseFee,
                'operator_fee' => $operatorFee,
                'paid_amount' => $paidAmount,
                'exchange_rate' => $exchangeRate,
                'paid_amount_currency' => $paidAmountCurrency,
            ]
        );
    }

    public static function fetchPaymentsForPublishersByAdsPaymentId(int $adsPaymentId): Collection
    {
        return self::select(
            [
                'publisher_id',
                DB::raw('SUM(paid_amount) AS paid_amount'),
            ]
        )->join(
            'network_cases',
            function (JoinClause $join) {
                $join->on('network_case_payments.network_case_id', '=', 'network_cases.id');
            }
        )->where('ads_payment_id', $adsPaymentId)->groupBy('publisher_id')->get();
    }

    public static function fetchPaymentsToExport(
        int $idFrom,
        int $caseIdMax,
        int $limit,
        int $offset
    ): Collection {
        return self::select(
            [
                'network_case_payments.*',
                'ads_payments.address as payer'
            ]
        )
            ->where('network_case_payments.id', '>=', $idFrom)
            ->where('network_case_payments.network_case_id', '<=', $caseIdMax)
            ->join(
                'ads_payments',
                'network_case_payments.ads_payment_id',
                '=',
                'ads_payments.id'
            )
            ->take($limit)
            ->skip($offset)
            ->get();
    }

    public function networkCase(): BelongsTo
    {
        return $this->belongsTo(NetworkCase::class);
    }
}
