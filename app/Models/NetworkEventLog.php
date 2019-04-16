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
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use function hex2bin;

/**
 * @property int created_at
 * @property int updated_at
 * @property string case_id
 * @property string event_id
 * @property string user_id
 * @property string banner_id
 * @property string publisher_id
 * @property string site_id
 * @property string zone_id
 * @property string event_type
 * @property string pay_from
 * @property string ip
 * @property string headers
 * @property string context
 * @property int human_score
 * @property string our_userdata
 * @property string their_userdata
 * @property int event_value
 * @property int $license_fee
 * @property int $operator_fee
 * @property int $paid_amount
 * @property int $exchange_rate
 * @property int $paid_amount_currency
 * @property int ads_payment_id
 * @property int is_view_clicked
 * @property string domain
 * @mixin Builder
 */
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
        'site_id',
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
        'license_fee',
        'operator_fee',
        'ads_payment_id',
        'is_view_clicked',
        'domain',
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
        'zone_id' => 'BinHex',
        'site_id' => 'BinHex',
        'pay_from' => 'AccountAddress',
        'ip' => 'BinHex',
        'headers' => 'JsonValue',
        'context' => 'JsonValue',
        'our_userdata' => 'JsonValue',
        'their_userdata' => 'JsonValue',
        'event_value' => 'Money',
        'paid_amount' => 'Money',
        'license_fee' => 'Money',
        'operator_fee' => 'Money',
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
        $collection = self::select(
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
        string $siteId,
        string $payFrom,
        $ip,
        $headers,
        ImpressionContext $context,
        $type
    ): void {
        $existedEventLog = self::where('event_id', hex2bin($eventId))->first();

        if ($existedEventLog) {
            return;
        }

        $banner = Banner::fetchBanner($bannerId);
        $landingUrl = $banner->campaign->landing_url ?? null;
        $domain = $landingUrl ? DomainReader::domain($landingUrl) : null;

        $log = new self();
        $log->case_id = $caseId;
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $zoneId;
        $log->publisher_id = $publisherId;
        $log->site_id = $siteId;
        $log->pay_from = $payFrom;
        $log->ip = $ip;
        $log->headers = $headers;
        $log->event_type = $type;
        $log->context = $context->toArray();
        $log->domain = $domain;
        $log->save();
    }

    public static function eventClicked(string $caseId): void
    {
        $a= self::where('case_id', hex2bin($caseId))
            ->where('event_type', self::TYPE_VIEW)
            ->update(['is_view_clicked' => 1]);
    }
}
