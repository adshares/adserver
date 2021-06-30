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

declare(strict_types=1);

namespace Adshares\Adserver\Client\Mapper\AdPay;

use Adshares\Adserver\Client\Mapper\JsonValueMapper;
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
                $mapped = self::mapEventLog($eventLog);
                $mapped['id'] = $eventLog->event_id;
                $mapped['time'] = $eventLog->created_at->getTimestamp();

                return $mapped;
            }
        )->toArray();
    }

    public static function mapConversionCollectionToArray(Collection $conversions): array
    {
        return $conversions->map(
            function (Conversion $conversion) {
                $event = $conversion->event;

                if (null === $event) {
                    return [];
                }

                $mapped = self::mapEventLog($event);
                $mapped['id'] = $conversion->uuid;
                $mapped['time'] = $conversion->created_at->getTimestamp();
                $mapped['conversion_id'] = $conversion->conversionDefinition->uuid;
                $mapped['conversion_value'] = $conversion->value;
                $mapped['group_id'] = $conversion->group_id;
                $mapped['payment_status'] = $event->payment_status;

                return $mapped;
            }
        )->filter()->toArray();
    }

    private static function mapEventLog(EventLog $event): array
    {
        return [
            'case_id' => $event->case_id,
            'case_time' => $event->created_at->getTimestamp(),
            'publisher_id' => $event->publisher_id,
            'zone_id' => $event->zone_id,
            'advertiser_id' => $event->advertiser_id,
            'campaign_id' => $event->campaign_id,
            'banner_id' => $event->banner_id,
            'impression_id' => $event->case_id,
            'tracking_id' => $event->tracking_id,
            'user_id' => $event->user_id ?? $event->tracking_id,
            'human_score' => (float)($event->human_score ?? AdUser::HUMAN_SCORE_ON_MISSING_KEYWORD),
            'page_rank' => (float)($event->page_rank ?? AdUser::PAGE_RANK_ON_MISSING_KEYWORD),
            'context' => JsonValueMapper::map($event->our_context),
            'keywords' => JsonValueMapper::map($event->our_userdata),
        ];
    }
}
