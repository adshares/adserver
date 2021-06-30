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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Utilities\DomainReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use stdClass;

use function hex2bin;

/**
 * @property Carbon created_at
 * @property int updated_at
 * @property string case_id
 * @property string event_id
 * @property string user_id
 * @property string tracking_id
 * @property string banner_id
 * @property string publisher_id
 * @property string advertiser_id
 * @property string campaign_id
 * @property string zone_id
 * @property string event_type
 * @property string pay_to
 * @property string our_context
 * @property array|stdClass their_context
 * @property float human_score
 * @property string our_userdata
 * @property string their_userdata
 * @property int $event_value_currency
 * @property int $exchange_rate
 * @property int $event_value
 * @property int $license_fee
 * @property int $operator_fee
 * @property int $paid_amount
 * @property int payment_id
 * @property int $payment_status
 * @property int is_view_clicked
 * @property string domain
 * @property int id
 * @mixin Builder
 */
class EventConversionLog extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;

    public const TYPE_VIEW = 'view';

    public const TYPE_CLICK = 'click';

    public const TYPE_SHADOW_CLICK = 'shadow-click';

    public const TYPE_CONVERSION = 'conversion';

    public const INDEX_CREATED_AT = 'event_conversion_logs_created_at_index';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'case_id',
        'event_id',
        'user_id',
        'tracking_id',
        'banner_id',
        'zone_id',
        'publisher_id',
        'advertiser_id',
        'campaign_id',
        'event_type',
        'pay_to',
        'our_context',
        'their_context',
        'human_score',
        'our_userdata',
        'their_userdata',
        'timestamp',
        'event_value_currency',
        'paid_amount',
        'payment_id',
        'payment_status',
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
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'case_id' => 'BinHex',
        'event_id' => 'BinHex',
        'user_id' => 'BinHex',
        'tracking_id' => 'BinHex',
        'zone_id' => 'BinHex',
        'banner_id' => 'BinHex',
        'publisher_id' => 'BinHex',
        'advertiser_id' => 'BinHex',
        'campaign_id' => 'BinHex',
        'pay_to' => 'AccountAddress',
        'our_context' => 'JsonValue',
        'their_context' => 'JsonValue',
        'our_userdata' => 'JsonValue',
        'their_userdata' => 'JsonValue',
    ];

    public static function create(
        string $caseId,
        string $eventId,
        string $bannerId,
        ?string $zoneId,
        string $trackingId,
        string $publisherId,
        string $campaignId,
        string $advertiserId,
        string $payTo,
        array $context,
        string $userData,
        string $type
    ): void {
        $existedEventLog = self::where('event_id', hex2bin($eventId))->first();

        if ($existedEventLog) {
            return;
        }

        $log = new self();
        $log->case_id = $caseId;
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->tracking_id = $trackingId;
        $log->zone_id = $zoneId;
        $log->publisher_id = $publisherId;
        $log->campaign_id = $campaignId;
        $log->advertiser_id = $advertiserId;
        $log->pay_to = $payTo;
        $log->their_context = $context;
        $log->their_userdata = $userData;
        $log->event_type = $type;
        $log->domain = self::fetchDomainFromMatchingEvent($type, $caseId) ?: self::getDomainFromContext($context);

        $log->save();
    }

    private static function fetchDomainFromMatchingEvent(string $type, string $caseId): ?string
    {
        if (self::TYPE_CLICK === $type || self::TYPE_SHADOW_CLICK === $type) {
            $eventId = Utils::createCaseIdContainingEventType($caseId, self::TYPE_VIEW);
            $viewEvent = self::where('event_id', hex2bin($eventId))->first();

            return $viewEvent->domain ?? null;
        }

        return null;
    }

    private static function getDomainFromContext(array $context): ?string
    {
        $headers = $context['device']['headers'];

        $domain = isset($headers['referer'][0]) ? DomainReader::domain($headers['referer'][0]) : null;

        if (!$domain || DomainReader::domain((string)config('app.serve_base_url')) === $domain) {
            return null;
        }

        return $domain;
    }
}
