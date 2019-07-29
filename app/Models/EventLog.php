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
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use function hex2bin;
use stdClass;

/**
 * @property int created_at
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
 * @property string ip
 * @property string headers
 * @property string our_context
 * @property string their_context
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
    
    public const TYPE_SHADOW_CLICK = 'shadow-click';

    public const TYPE_CONVERSION = 'conversion';

    public const INDEX_CREATED_AT = 'event_logs_created_at_index';

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
        'ip',
        'headers',
        'our_context',
        'their_context',
        'human_score',
        'our_userdata',
        'their_userdata',
        'timestamp',
        'event_value_currency',
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
        'tracking_id' => 'BinHex',
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

    public static function fetchUnpaidEvents(int $limit = null): Collection
    {
        $query = self::whereNotNull('event_value_currency')
            ->where('event_value_currency', '>', 0)
            ->whereNotNull('pay_to')
            ->whereNull('payment_id')
            ->where('created_at', '>', (new DateTime())->modify('-24 hour'));

        if ($limit !== null) {
            $query->limit($limit);
        }

        if (DB::isMySql()) {
            $query->getQuery()->fromRaw($query->getQuery()->from.' FORCE INDEX ('.self::INDEX_CREATED_AT.')');
        }

        return $query->get();
    }

    public static function fetchEvents(Arrayable $paymentIds, int $limit, int $offset): Collection
    {
        return self::whereIn('payment_id', $paymentIds)
            ->limit($limit)
            ->offset($offset)
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
        $log->ip = $ip;
        $log->headers = $headers;
        $log->their_context = $context;
        $log->their_userdata = $userData;
        $log->event_type = $type;

        $log->domain = isset($headers['referer'][0]) ? DomainReader::domain($headers['referer'][0]) : null;

        if ($type === self::TYPE_CLICK || $type === self::TYPE_SHADOW_CLICK) {
            $eventId = Utils::createCaseIdContainingEventType($caseId, self::TYPE_VIEW);
            $viewEvent = self::where('event_id', hex2bin($eventId))->first();

            $log->domain = $viewEvent->domain ?? null;
        }

        $log->save();
    }

    public static function createWithUserData(
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
        string $theirUserData,
        string $type,
        ?float $humanScore,
        ?stdClass $ourUserData
    ): self {
        $existedEventLog = self::where('event_id', hex2bin($eventId))->first();

        if ($existedEventLog) {
            return $existedEventLog;
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
        $log->ip = $ip;
        $log->headers = $headers;
        $log->their_context = $context;
        $log->their_userdata = $theirUserData;
        $log->event_type = $type;

        if ($type === self::TYPE_CLICK || $type === self::TYPE_SHADOW_CLICK) {
            $eventId = Utils::createCaseIdContainingEventType($caseId, self::TYPE_VIEW);
            $viewEvent = self::where('event_id', hex2bin($eventId))->first();

            $log->domain = $viewEvent->domain ?? null;
        }

        $log->human_score = $humanScore;
        $log->our_userdata = $ourUserData;
        
        $log->save();
        
        return $log;
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

    public static function fetchByEventIds(array $eventIds): Collection
    {
        $binEventIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $eventIds
        );

        return self::whereIn('event_id', $binEventIds)->get();
    }

    public static function fetchLastByTrackingId(string $campaignPublicId, string $trackingId): ?self
    {
        return self::where('campaign_id', hex2bin($campaignPublicId))
            ->where('tracking_id', hex2bin($trackingId))
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public static function eventClicked(string $caseId): int
    {
        $eventId = Utils::createCaseIdContainingEventType($caseId, self::TYPE_VIEW);

        return self::where('event_id', hex2bin($eventId))
            ->update(['is_view_clicked' => 1]);
    }

    public function updateWithUserContext(UserContext $userContext): void
    {
        $userId = $userContext->userId();
        if ($userId) {
            $this->user_id = Uuid::fromString($userId)->hex();
        }
        $this->human_score = $userContext->humanScore();
        $this->our_userdata = $userContext->keywords();
    }

    public function updateWithUserData(float $humanScore, stdClass $userData): void
    {
        $this->human_score = $humanScore;
        $this->our_userdata = $userData;
    }

    public function group(): HasOne
    {
        return $this->hasOne(ConversionGroup::class);
    }
}
