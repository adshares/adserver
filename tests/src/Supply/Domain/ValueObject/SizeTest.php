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

namespace Adshares\Tests\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Size;
use PHPUnit\Framework\TestCase;

final class SizeTest extends TestCase
{
    public function testIsValidSize(): void
    {
        $this->assertTrue(Size::isValid('300x250'));
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

    public function testAspect(): void
    {
        $this->assertEquals('4:3', Size::getAspect(320, 240));
        $this->assertEquals('6:5', Size::getAspect(300, 250));
        $this->assertEquals('', Size::getAspect(320, 0));
        $this->assertEquals('', Size::getAspect(0, 240));
    }

    public function testFindBestFit(): void
    {
        $this->assertContains('300x250', Size::findBestFit(300, 250, 0, 1));
        $this->assertContains('336x280', Size::findBestFit(330, 270, 0, 1));
        $this->assertContains('cube', Size::findBestFit(330, 270, 10, 1));
    }

    public function testFindMatching(): void
    {
        $this->assertEmpty(Size::findMatching(1, 1));
        $this->assertEmpty(Size::findMatching(300, 0));
        $this->assertEmpty(Size::findMatching(300, 10));
        $this->assertEmpty(Size::findMatching(3000, 4000));
        $this->assertEmpty(Size::findMatching(4000, 3000));
        $this->assertContains('300x250', Size::findMatching(300, 250));
        $this->assertContains('300x250', Size::findMatching(320, 240));
        $this->assertContains('580x400', Size::findMatching(1920, 1080));
        $this->assertContains('300x600', Size::findMatching(1080, 1920));
    }
}
