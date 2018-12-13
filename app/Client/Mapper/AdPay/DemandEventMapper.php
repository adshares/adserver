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
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use stdClass;

class DemandEventMapper
{
    public static function mapEventCollectionToEventArray(Collection $events): array
    {
        $eventArray = $events->map(
            function (EventLog $event) {
                $eventArray = $event->toArray();

                $timestamp = (new DateTime($eventArray['created_at']))->getTimestamp();

                $theirKeywords = self::processKeywords($eventArray['their_userdata']);
                $ourKeywords = self::processKeywords($eventArray['our_userdata']);

                $mapped = [
                    'banner_id' => $eventArray['banner_id'],
                    'event_type' => $eventArray['event_type'],
                    'event_id' => $eventArray['event_id'],
                    'timestamp' => $timestamp,
                    'their_keywords' => $theirKeywords,
                    'our_keywords' => $ourKeywords,
                    'human_score' => $eventArray['human_score'] ?? 0,
                    'publisher_id' => $eventArray['publisher_id'],
                    'user_id' => $eventArray['user_id']
                ];

                if ($eventArray['event_value'] !== null) {
                    $mapped['event_value'] = $eventArray['event_value'];
                }

                return $mapped;
            }
        )->toArray();

        return $eventArray;
    }

    private static function processKeywords($keywords)
    {
        if ($keywords === null) {
            return new stdClass();
        }

        return array_fill_keys(explode(',', $keywords), 1);
    }
}
