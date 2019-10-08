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

namespace Adshares\Demand\Application\Dto;

use DateTime;
use Illuminate\Contracts\Support\Arrayable;

class AdPayEvents implements Arrayable
{
    /** @var DateTime */
    private $timeStart;

    /** @var DateTime */
    private $timeEnd;

    /** @var array */
    private $events;

    public function __construct(DateTime $timeStart, DateTime $timeEnd, array $events)
    {
        $this->timeStart = $timeStart;
        $this->timeEnd = $timeEnd;
        $this->events = $events;
    }

    public function toArray()
    {
        return [
            'time_start' => $this->timeStart->getTimestamp(),
            'time_end' => $this->timeEnd->getTimestamp(),
            'events' => $this->events,
        ];
    }
}
