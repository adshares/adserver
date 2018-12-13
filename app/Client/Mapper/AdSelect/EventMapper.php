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

use stdClass;
use function substr;

class EventMapper
{
    public static function map($event): array
    {
        $keywords = self::normalizeKeywords($event['context']->site->keywords) ?? new stdClass();
        $mappedEvent = [
            'keywords' => $keywords,
            'publisher_id' => $event['publisher_id'] ?? null,
            'banner_id' => $event['banner_id'],
            'user_id' => $event['user_id'],
            'event_id' => $event['event_id'],
            'event_type' => $event['event_type'],
            'paid_amount' => 0,
        ];

        return $mappedEvent;
    }

    private static function normalizeKeywords(?array $keywords = [])
    {
        $mappedKeywords = [];

        foreach ($keywords as $keyword) {
            $lastOccurence = strrpos($keyword, ':');

            $key = substr($keyword, 0, $lastOccurence);
            $value = substr($keyword, $lastOccurence + 1);

            $mappedKeywords[$key] = $value;
        }

        return $mappedKeywords;
    }
}
