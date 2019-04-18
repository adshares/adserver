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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use function hex2bin;

/**
 * @property int created_at
 * @property int updated_at
 * @property string case_id
 * @property string event_id
 * @property string user_id
 * @property string banner_id
 * @property string publisher_id
 * @property string advertiser_id
 * @property string campaign_id
 * @property string zone_id
 * @property string event_type
 * @property string pay_to
 * @property string ip
 * @property string headers
 * @property string our_context
 * @property string their_context
 * @property int human_score
 * @property string our_userdata
 * @property string their_userdata
 * @property int event_value
 * @property int licence_fee
 * @property int operator_fee
 * @property int paid_amount
 * @property int payment_id
 * @property int reason
 * @property int is_view_clicked
 * @property string domain
 * @property int id
 * @mixin Builder
 */
class EventLog extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;

    public const TYPE_REQUEST = 'request';

    public const TYPE_VIEW = 'view';

    public const TYPE_CLICK = 'click';

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
        'advertiser_id',
        'campaign_id',
        'event_type',
        'pay_to',
        'ip',
        'headers',
        'our_context',
        'their_context',
        'human_score',
        'our_userdata',
        'their_userdata',
        'timestamp',
        'event_value',
        'paid_amount',
        'payment_id',
        'reason',
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
        'zone_id' => 'BinHex',
        'banner_id' => 'BinHex',
        'publisher_id' => 'BinHex',
        'advertiser_id' => 'BinHex',
        'campaign_id' => 'BinHex',
        'pay_to' => 'AccountAddress',
        'ip' => 'BinHex',
        'headers' => 'JsonValue',
        'our_context' => 'JsonValue',
        'their_context' => 'JsonValue',
        'our_userdata' => 'JsonValue',
        'their_userdata' => 'JsonValue',
    ];

    public static function fetchUnpaidEvents(): Collection
    {
        $query = self::whereNotNull('event_value')
            ->where('event_value', '>', 0)
            ->whereNotNull('pay_to')
            ->whereNull('payment_id')
            ->orderBy('pay_to');

        return $query->get();
    }

    public static function fetchEvents(Arrayable $paymentIds): Collection
    {
        return self::whereIn('payment_id', $paymentIds)
            ->get();
    }

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
        $ip,
        $headers,
        array $context,
        string $userData,
        $type
    ): void {
        $existedEventLog = self::where('event_id', hex2bin($eventId))->first();

        if ($existedEventLog) {
            return;
        }

        $log = new self();
        $log->case_id = $caseId;
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $zoneId;
        $log->publisher_id = $publisherId;
        $log->campaign_id = $campaignId;
        $log->advertiser_id = $advertiserId;
        $log->pay_to = $payTo;
        $log->ip = $ip;
        $log->headers = $headers;
        $log->their_context = $context;
        $log->their_userdata = $userData;
        $log->event_type = $type;

        if ($type === self::TYPE_CLICK) {
            $viewEvent = self::where('case_id', hex2bin($caseId))
                ->where('event_type', self::TYPE_VIEW)
                ->first();

            $log->domain = $viewEvent->domain ?? null;
        }

        $log->save();
    }

    public static function fetchOneByEventId(string $eventId): self
    {
        $event = self::where('event_id', hex2bin($eventId))->first();

        if (!$event) {
            throw (new ModelNotFoundException('Model not found'))
                ->setModel(self::class, [$eventId]);
        }

        return $event;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function impressionContext(): ImpressionContext
    {
        $headersArray = get_object_vars($this->headers);

        $refererList = $headersArray['referer'] ?? [];
        $domain = $refererList[0] ?? '';

        $ip = inet_ntop(hex2bin($this->ip));

        $userAgentList = $headersArray['user-agent'];
        $ua = $userAgentList[0] ?? '';

        return new ImpressionContext(
            ['domain' => $domain, 'page' => $domain],
            ['ip' => $ip, 'ua' => $ua],
            ['uid' => Utils::trackingIdFromBinUserId(hex2bin($this->user_id))]
        );
    }

    public static function eventClicked(string $caseId): void
    {
        self::where('case_id', hex2bin($caseId))
            ->where('event_type', self::TYPE_VIEW)
            ->update(['is_view_clicked' => 1]);
    }
}
