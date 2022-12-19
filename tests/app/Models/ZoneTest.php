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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Adserver\ViewModel\ZoneSize;
use Adshares\Common\Exception\InvalidArgumentException;

class ZoneTest extends TestCase
{
    public function testFetchOrCreateWhileInvalidSiteId(): void
    {
        self::expectException(InvalidArgumentException::class);

        Zone::fetchOrCreate(PHP_INT_MAX, new ZoneSize(300, 200), Zone::DEFAULT_NAME);
    }

    public function testFetchOrCreateWhileNoMatchingScope(): void
    {
        /** @var Site $site */
        $site = Site::factory(['medium' => MediumName::Metaverse->value, 'vendor' => 'decentraland'])->create();
        self::expectException(InvalidArgumentException::class);

        Zone::fetchOrCreate($site->id, new ZoneSize(5000, 10), Zone::DEFAULT_NAME);
    }
}
