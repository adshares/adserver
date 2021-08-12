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

use Adshares\Adserver\Utilities\CssUtils;
use PHPUnit\Framework\TestCase;

final class CssUtilsTest extends TestCase
{
    public function testIsArrayNotAssociative(): void
    {
        self::assertEquals('foo', CssUtils::normalizeClass('foo'));
        self::assertEquals('_foo', CssUtils::normalizeClass('_foo'));
        self::assertEquals('_123', CssUtils::normalizeClass('_123'));
        self::assertEquals('-foo', CssUtils::normalizeClass('-foo'));
        self::assertEquals('-a--foo', CssUtils::normalizeClass('-a--foo'));
        self::assertEquals('_-1--foo', CssUtils::normalizeClass('-1--foo'));
        self::assertEquals('_--foo', CssUtils::normalizeClass('--foo'));
        self::assertEquals('_1foo', CssUtils::normalizeClass('1foo'));
        self::assertEquals('_1foo', CssUtils::normalizeClass('1foo'));
        self::assertEquals('f_oo_', CssUtils::normalizeClass('f#oo;'));
        self::assertEquals('_1f_oo_', CssUtils::normalizeClass('1f#oo;'));
        self::assertEquals('_1foo', CssUtils::normalizeClass('#1foo'));
    }
}
