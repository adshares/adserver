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

declare(strict_types = 1);

namespace Adshares\Adserver\Client\Mapper\AdPay;

use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Application\Service\AdUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use stdClass;

class DemandEventLegacyMapper
{
    public static function mapEventCollectionToEventArray(Collection $events): array
    {
        return $events->map(
            function (EventLog $event) {
                /** @var Carbon $createdAt */
                $createdAt = $event->created_at;
                $timestamp = $createdAt->getTimestamp();

                $theirKeywords = new stdClass();//self::processTheirKeywords($event->their_userdata);
                $ourUserData = json_decode(json_encode($event->our_userdata), true);
                $ourKeywords = OurKeywordsLegacyMapper::map($ourUserData);

                $mapped = [
                    'banner_id' => $event->banner_id,
                    'case_id' => $event->case_id,
                    'event_type' => $event->event_type,
                    'event_id' => $event->event_id,
                    'timestamp' => $timestamp,
                    'their_keywords' => $theirKeywords,
                    'our_keywords' => $ourKeywords,
                    'human_score' => (float)($event->human_score ?? AdUser::HUMAN_SCORE_ON_MISSING_KEYWORD),
                    'user_id' => $event->user_id ?? $event->tracking_id,
                ];

                if ($event->publisher_id !== null) {
                    $mapped['publisher_id'] = $event->publisher_id;
                }

                if ($event->event_value !== null) {
                    $mapped['event_value'] = $event->event_value;
                }

                return $mapped;
            }
        )->toArray();
    }

    private static function processTheirKeywords($keywords)
    {
        if (!$keywords) {
            return new stdClass();
        }

        return array_fill_keys(explode(',', $keywords), 1);
    }
}
