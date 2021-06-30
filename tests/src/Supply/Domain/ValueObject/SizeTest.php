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

namespace Adshares\Test\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Size;
use PHPUnit\Framework\TestCase;

final class SizeTest extends TestCase
{
    public function testIsValidSize(): void
    {
        $this->assertTrue(Size::isValid(array_keys(Size::SIZE_INFOS)[0]));
        $this->assertFalse(Size::isValid('152x1'));
        $this->assertFalse(Size::isValid('00x0'));
        $this->assertFalse(Size::isValid(''));
    }

    public function testDimensions(): void
    {
        $this->assertEquals('728x90', Size::fromDimensions(728, 90));
        $this->assertEquals('1x0', Size::fromDimensions(1, 0));
        $this->assertEquals([728, 90], Size::toDimensions('728x90'));
        $this->assertEquals([0, 90], Size::toDimensions('x90'));
        $this->assertEquals([728, 0], Size::toDimensions('728'));
        $this->assertEquals([0, 0], Size::toDimensions(''));
    }
}
