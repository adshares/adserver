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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Http\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testIfCreateTrackingIdsAreTheSameWhenImpressionIdExists()
    {
        $impressionId = '1234567qweasd';

        $trackingId1 = Utils::createTrackingId($impressionId);
        $trackingId2 = Utils::createTrackingId($impressionId);

        $this->assertEquals($trackingId1, $trackingId2);
    }

    public function testIfCreateTrackingIdsAreDifferentWhenNoImpressionId()
    {

        $trackingId1 = Utils::createTrackingId();
        $trackingId2 = Utils::createTrackingId();

        $this->assertNotEquals($trackingId1, $trackingId2);
    }
}
