<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\ViewModel;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ZoneSize;

class ZoneSizeTest extends TestCase
{
    public function testBanner(): void
    {
        $zoneSize = new ZoneSize(100, 61);

        self::assertEquals(100, $zoneSize->getWidth());
        self::assertEquals(61, $zoneSize->getHeight());
        self::assertEquals(0, $zoneSize->getDepth());
        self::assertEquals('100x61', $zoneSize->toString());
    }

    public function testCube(): void
    {
        $zoneSize = new ZoneSize(100, 61, 73);

        self::assertEquals(100, $zoneSize->getWidth());
        self::assertEquals(61, $zoneSize->getHeight());
        self::assertEquals(73, $zoneSize->getDepth());
        self::assertEquals('cube', $zoneSize->toString());
    }
}
