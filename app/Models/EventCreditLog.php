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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property DateTimeInterface created_at
 * @property DateTimeInterface updated_at
 * @property string uuid
 * @property DateTimeInterface computed_at
 * @property string advertiser_id
 * @property string campaign_id
 * @property string pay_to
 * @property int event_value_currency
 * @property float exchange_rate
 * @property int event_value
 * @property int license_fee
 * @property int operator_fee
 * @property int community_fee
 * @property int paid_amount
 * @property int|null payment_id
 * @mixin Builder
 */
class EventCreditLog extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use HasFactory;

    protected $dates = [
        'computed_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $hidden = [];

    protected array $traitAutomate = [
        'uuid' => 'BinHex',
        'advertiser_id' => 'BinHex',
        'campaign_id' => 'BinHex',
        'pay_to' => 'AccountAddress',
    ];

    public static function create(
        DateTimeInterface $computedAt,
        string $advertiserId,
        string $campaignId,
        string $payTo,
        int $eventValueCurrency,
        float $exchangeRate,
        int $eventValue,
        int $licenseFee,
        int $operatorFee,
        int $communityFee,
        int $paidAmount,
    ): void {
        $log = new self();
        $log->computed_at = $computedAt;
        $log->advertiser_id = $advertiserId;
        $log->campaign_id = $campaignId;
        $log->pay_to = $payTo;
        $log->event_value_currency = $eventValueCurrency;
        $log->exchange_rate = $exchangeRate;
        $log->event_value = $eventValue;
        $log->license_fee = $licenseFee;
        $log->operator_fee = $operatorFee;
        $log->community_fee = $communityFee;
        $log->paid_amount = $paidAmount;
        $log->save();
    }
}
