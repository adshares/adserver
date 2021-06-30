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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\UuidStringGenerator;
use PHPUnit\Framework\TestCase;

class UuidStringGeneratorTest extends TestCase
{
    public function testV4(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $uuid = UuidStringGenerator::v4();

            self::assertSame(1, preg_match('/^[0-9a-f]{32}$/', $uuid), 'Invalid uuid: ' . $uuid);
            self::assertSame('4', substr($uuid, 12, 1), 'Invalid version in uuid: ' . $uuid);
        }
    }
}
