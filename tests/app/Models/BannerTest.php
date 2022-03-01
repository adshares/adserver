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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Tests\TestCase;

class BannerTest extends TestCase
{
    /**
     * @dataProvider typeToTextTypeProvider
     */
    public function testMapTypeToTextType(int $type, string $textType): void
    {
        self::assertEquals($textType, Banner::type($type));
    }

    public function testMapTypeToDefaultTextType(): void
    {
        self::assertEquals('direct', Banner::type(5));
    }

    /**
     * @dataProvider typeToTextTypeProvider
     */
    public function testMapTextTypeToType(int $type, string $textType): void
    {
        self::assertEquals($type, Banner::typeAsInteger($textType));
    }

    public function testMapTextTypeToDefaultType(): void
    {
        self::assertEquals(2, Banner::typeAsInteger('default'));
    }

    /**
     * @dataProvider typeToTextTypeProvider
     */
    public function testAllowedTypes(int $type, string $textType): void
    {
        self::assertContains($textType, Banner::types());
    }

    public function typeToTextTypeProvider(): array
    {
        return [
            [0, 'image'],
            [1, 'html'],
            [2, 'direct'],
            [3, 'video'],
            [4, 'model'],
        ];
    }
}
