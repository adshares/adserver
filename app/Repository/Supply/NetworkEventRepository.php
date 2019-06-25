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

namespace Adshares\Adserver\Repository\Supply;

use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Supply\Domain\Repository\EventRepository;
use DateTime;

class NetworkEventRepository implements EventRepository
{
    public function fetchUnpaidEventsBetweenIds(
        int $eventIdFirst,
        int $eventIdLast,
        int $limit = self::PACKAGE_SIZE,
        int $offset = 0
    ): array {
        $events = NetworkEventLog::whereBetween('id', [$eventIdFirst, $eventIdLast])
            ->take($limit)
            ->skip($offset)
            ->get();

        return $events->toArray();
    }

    public function fetchPaidEventsUpdatedAfterAdsPaymentId(
        int $eventPaymentIdFirst,
        int $eventPaymentIdLast,
        int $limit = self::PACKAGE_SIZE,
        int $offset = 0
    ): array {
        $events = NetworkEventLog::whereBetween('ads_payment_id', [$eventPaymentIdFirst, $eventPaymentIdLast])
            ->take($limit)
            ->skip($offset)
            ->get();
        return $events->toArray();
    }

    public function fetchLastUnpaidEventsByDate(DateTime $date): int
    {
        $event = NetworkEventLog::where('created_at', '>=', $date)
            ->whereNull('ads_payment_id')
            ->first();

        return $event->id ?? 0;
    }

    public function fetchLastPaidEventsByDate(DateTime $date): int
    {
        $event = NetworkEventLog::where('created_at', '>=', $date)
            ->whereNotNull('ads_payment_id')
            ->first();

        return $event->ads_payment_id ?? 0;
    }
}
