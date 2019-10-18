<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Client\Mapper\AdPay;

use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Application\Service\AdUser;
use Illuminate\Database\Eloquent\Collection;

class DemandEventMapper
{
    public static function mapEventCollectionToArray(Collection $events): array
    {
        return $events->map(
            function (EventLog $eventLog) {
                return self::mapEventLog($eventLog);
            }
        )->toArray();
    }

    public static function mapConversionCollectionToArray(Collection $conversions): array
    {
        return $conversions->map(
            function (Conversion $conversion) {
                $event = $conversion->event;

                $mapped = self::mapEventLog($event);
                $mapped['conversion_id'] = $conversion->uuid;
                $mapped['conversion_value'] = $conversion->value;
                $mapped['group_id'] = $conversion->group_id;
                $mapped['payment_status'] = $event->payment_status;

                return $mapped;
            }
        )->toArray();
    }

    private static function mapEventLog(EventLog $event): array
    {
        return [
            'id' => $event->event_id,
            'time' => $event->created_at->getTimestamp(),
            'case_id' => $event->case_id,
            'publisher_id' => $event->publisher_id,
            'zone_id' => $event->zone_id,
            'advertiser_id' => $event->banner_id,
            'campaign_id' => $event->campaign_id,
            'banner_id' => $event->advertiser_id,
            'impression_id' => $event->case_id,
            'tracking_id' => $event->tracking_id,
            'user_id' => $event->user_id ?? $event->tracking_id,
            'human_score' => (float)($event->human_score ?? AdUser::HUMAN_SCORE_ON_MISSING_KEYWORD),
            'context' => JsonValueMapper::map($event->our_context),
            'keywords' => JsonValueMapper::map($event->our_userdata),
        ];
    }
}
