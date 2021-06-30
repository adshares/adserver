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

namespace Adshares\Adserver\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use DateTime;
use DateTimeInterface;
use stdClass;

class EventMapper
{
    public static function map($event): array
    {
        $keywords = self::getNormalizedKeywordsFromEvent($event);
        if (!$keywords) {
            $keywords = new stdClass();
        }

        $mappedEvent = [
            'id' => $event['id'],
            'keywords' => $keywords,
            'publisher_id' => $event['publisher_id'],
            'banner_id' => $event['banner_id'],
            'user_id' => $event['user_id'] ?? $event['tracking_id'],
            'tracking_id' => $event['tracking_id'],
            'campaign_id' => $event['campaign_id'],
            'event_id' => $event['event_id'],
            'type' => $event['event_type'],
            'zone_id' => $event['zone_id'],
            'case_id' => $event['case_id'],
            'time' => DateTime::createFromFormat('Y-m-d H:i:s', $event['created_at'])->format(DateTimeInterface::ATOM),
        ];

        return $mappedEvent;
    }

    private static function getNormalizedKeywordsFromEvent($event): ?array
    {
        $keywords = null;
        $eventContext = $event['context'];
        if (is_object($eventContext) && property_exists($eventContext, 'site')) {
            $keywords = AbstractFilterMapper::generateNestedStructure(['site' => (array)$eventContext->site->keywords]);
        }

        return $keywords;
    }
}
