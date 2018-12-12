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

namespace Adshares\Supply\Application\Service;

use Adshares\Supply\Application\Service\Exception\NoEventsForGivenTimePeriod;
use Adshares\Supply\Domain\Repository\EventRepository;
use DateTime;

class AdSelectEventExporter
{
    private $client;

    private $eventRepository;

    public function __construct(AdSelect $client, EventRepository $eventRepository)
    {
        $this->client = $client;
        $this->eventRepository = $eventRepository;
    }

    public function export(DateTime $from): void
    {
        $events = $this->eventRepository->fetchEventsFromDate($from);

        if (!$events) {
            throw new NoEventsForGivenTimePeriod(sprintf(
                'No found events from: %s. Current time: %s',
                $from->format(DateTime::ATOM),
                (new DateTime())->format(DateTime::ATOM)
            ));
        }

        // @todo update publisher_id

        $this->client->exportEvents($events);
    }
}
