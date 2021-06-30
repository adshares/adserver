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

namespace Adshares\Tests\Demand\Application\Dto;

use Adshares\Demand\Application\Dto\AdPayEvents;
use DateTime;
use PHPUnit\Framework\TestCase;

final class AdPayEventsTest extends TestCase
{
    public function testToArray(): void
    {
        $timestampStart = 1572012000;
        $timestampEnd = 1572015600;

        $expected = [
            'time_start' => $timestampStart,
            'time_end' => $timestampEnd,
            'events' => [],
        ];

        $adPayEvents = new AdPayEvents(new DateTime('@' . $timestampStart), new DateTime('@' . $timestampEnd), []);

        $this->assertEquals($expected, $adPayEvents->toArray());
    }
}
