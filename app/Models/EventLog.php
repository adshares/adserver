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

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int event_id
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
        'banner_id' => 'BinHex',
        'publisher_id' => 'BinHex',
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
            ->whereNotNull('pay_to')
            ->whereNull('payment_id')
            ->orderBy('pay_to');

        return $query->get();
    }

    public static function fetchEvents(int $paymentId): Collection
    {
        return self::where('payment_id', $paymentId)
            ->get();
    }

    public static function create(
        string $caseId,
        string $eventId,
        string $bannerId,
        string $zoneId,
        string $trackingId,
        string $publisherId,
        string $payTo,
        $ip,
        $headers,
        array $context,
        string $userData,
        $type
    ): self {
        $log = new self();
        $log->case_id = $caseId;
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $zoneId;
        $log->publisher_id = $publisherId;
        $log->pay_to = $payTo;
        $log->ip = $ip;
        $log->headers = $headers;
        $log->their_context = $context;
        $log->their_userdata = $userData;
        $log->event_type = $type;
        $log->save();

        return $log;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createImpressionContext(): ImpressionContext
    {
        // TODO input data should be validated - currently ErrorException could be thrown
        $ip = inet_ntop(hex2bin($this->ip));

        $headersArray = get_object_vars($this->headers);

        $domain = $headersArray['referer'][0];
        $ua = $headersArray['user-agent'][0];

        $cookieHeader = $headersArray['cookie'][0];
        $cookies = explode(';', $cookieHeader);
        foreach ($cookies as $cookie) {
            $arr = explode('=', $cookie, 2);
            if ((count($arr) === 2) && trim($arr[0]) === 'tid') {
                $tid = trim($arr[1]);
                break;
            }
        }

        $site = ['domain' => $domain];
        $device = ['ip' => $ip, 'ua' => $ua];
        $user = ['uid' => $tid];

        return new ImpressionContext($site, $device, $user);
    }
}
