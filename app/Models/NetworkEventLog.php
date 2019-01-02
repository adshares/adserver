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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Models\Traits\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NetworkEventLog extends Model
{
    public const TYPE_VIEW = 'view';

    public const TYPE_CLICK = 'click';

    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;
    use Money;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'case_id',
        'event_id',
        'user_id',
        'banner_id',
        'zone_id',
        'publisher_id',
        'pay_from',
        'event_type',
        'ip',
        'headers',
        'context',
        'human_score',
        'our_userdata',
        'their_userdata',
        'timestamp',
        'event_value',
        'paid_amount',
        'licence_fee_amount',
        'operator_fee_amount',
        'ads_payment_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
        'case_id' => 'BinHex',
        'event_id' => 'BinHex',
        'user_id' => 'BinHex',
        'banner_id' => 'BinHex',
        'publisher_id' => 'BinHex',
        'pay_from' => 'AccountAddress',
        'ip' => 'BinHex',
        'headers' => 'JsonValue',
        'context' => 'JsonValue',
        'our_userdata' => 'JsonValue',
        'their_userdata' => 'JsonValue',
        'event_value' => 'Money',
        'paid_amount' => 'Money',
        'licence_fee_amount' => 'Money',
        'operator_fee_amount' => 'Money',
    ];

    public static function fetchByCaseId(string $caseId): Collection
    {
        return self::where('case_id', hex2bin($caseId))->get();
    }

    public static function fetchByEventId(string $eventId): ?NetworkEventLog
    {
        return self::where('event_id', hex2bin($eventId))->first();
    }

    public static function fetchPaymentsForPublishersByAdsPaymentId(int $adsPaymentId): Collection
    {
        $collection = DB::table(self::getTableName())->select(
            'publisher_id',
            DB::raw('SUM(paid_amount) as paid_amount')
        )->where('ads_payment_id', $adsPaymentId)->groupBy('publisher_id')->get();

        return $collection;
    }

    public static function getTableName()
    {
        return with(new static())->getTable();
    }

    public static function create(
        string $caseId,
        string $eventId,
        string $bannerId,
        string $zoneId,
        string $trackingId,
        string $publisherId,
        string $payFrom,
        $ip,
        $headers,
        array $context,
        $type
    ): self {
        $log = new self();
        $log->case_id = $caseId;
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $zoneId;
        $log->publisher_id = $publisherId;
        $log->pay_from = $payFrom;
        $log->ip = $ip;
        $log->headers = $headers;
        $log->event_type = $type;
        $log->context = $context;
        $log->save();

        return $log;
    }
}
