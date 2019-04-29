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
use function sprintf;

class AdSelectEventExporter
{
    public $exportedEvents = 0;

    private $client;

    private $eventRepository;

    public function __construct(AdSelect $client, EventRepository $eventRepository)
    {
        $this->client = $client;
        $this->eventRepository = $eventRepository;
    }

    public function export(DateTime $from): void
    {
        $events = $this->eventRepository->fetchEventsCreatedFromDate($from);

        if (!$events) {
            throw new NoEventsForGivenTimePeriod(
                sprintf(
                    'Events from: %s not found. Current time: %s',
                    $from->format(DateTime::ATOM),
                    (new DateTime())->format(DateTime::ATOM)
                )
            );
        }

        $this->client->exportEvents($events);
    }

    public function exportPayments(DateTime $from): void
    {
        $offset = 0;

        do {
            $events = $this->eventRepository->fetchPaidEventsUpdatedFromDate(
                $from,
                EventRepository::PACKAGE_SIZE,
                $offset
            );

            $this->client->exportEventsPayments($events);
            $this->exportedEvents += count($events);

            $offset += EventRepository::PACKAGE_SIZE;
        } while (count($events) === EventRepository::PACKAGE_SIZE);
    }

    public function numberOfExportedEvents(): int
    {
        return $this->exportedEvents;
    }
}
