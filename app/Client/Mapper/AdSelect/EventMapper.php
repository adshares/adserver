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

namespace Adshares\Adserver\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
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
            'keywords' => $keywords,
            'publisher_id' => $event['publisher_id'],
            'banner_id' => $event['banner_id'],
            'user_id' => $event['user_id'] ?? $event['tracking_id'],
            'event_id' => $event['event_id'],
            'event_type' => $event['event_type'],
        ];

        return $mappedEvent;
    }

    private static function getNormalizedKeywordsFromEvent($event): ?array
    {
        $keywords = null;
        $eventContext = $event['context'];
        if (is_object($eventContext) && property_exists($eventContext, 'site')) {
            $keywords = AbstractFilterMapper::generateNestedStructure(['site'=>(array)$eventContext->site->keywords]);
        }

        return $keywords;
    }
}
