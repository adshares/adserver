<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Test\Common\Domain\Model;

use PHPUnit\Framework\TestCase;

use Adshares\Common\Domain\Model\Uuid;

final class UuidTest extends TestCase
{
    public function testReturningAnIdWhenObjectIsCasted(): void
    {
        $uuid = new Uuid();

        $this->assertEquals($uuid, (string)$uuid);
    }
}
