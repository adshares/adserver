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

use Adshares\Adserver\Utilities\ArrayUtils;
use PHPUnit\Framework\TestCase;

final class ArrayUtilsTest extends TestCase
{
    /**
     * @dataProvider isAssociativeProvider
     *
     * @param array $data
     */
    public function testIsArrayAssociative(array $data): void
    {
        self::assertTrue(ArrayUtils::isAssoc($data));
    }

    public function isAssociativeProvider(): array
    {
        return [
            [['a' => 'foo']],
            [['a' => 'foo', 'b' => 'bar']],
            [[1 => 'foo', 2 => 'bar']],
        ];
    }

    /**
     * @dataProvider isNotAssociativeProvider
     *
     * @param array $data
     */
    public function testIsArrayNotAssociative(array $data): void
    {
        self::assertFalse(ArrayUtils::isAssoc($data));
    }

    public function isNotAssociativeProvider(): array
    {
        return [
            [['foo']],
            [['foo', 'bar']],
            [[0 => 'foo', 1 => 'bar']],
        ];
    }

    /**
     * @dataProvider deepMergeProvider
     *
     * @param array $expectedData
     * @param array $data
     */
    public function testDeepMerge(array $expectedData, array ...$data): void
    {
        self::assertEquals($expectedData, ArrayUtils::deepMerge(...$data));
    }

    public function deepMergeProvider(): array
    {
        return [
            [[], [], []],
            [['foo'], ['foo'], ['foo']],
            [['foo', 'bar'], ['foo'], ['bar']],
            [['a' => 'foo'], ['a' => 'foo'], ['a' => 'foo']],
            [['a' => 'bar'], ['a' => 'foo'], ['a' => 'bar']],
            [['a' => 'foo', 'b' => 'bar'], ['a' => 'foo'], ['b' => 'bar']],
            [
                ['site' => ['domain' => ['example1.com', 'example2.com']]],
                ['site' => ['domain' => ['example1.com']]],
                ['site' => ['domain' => ['example2.com']]],
            ],
            [
                ['site' => ['domain' => ['example.com'], 'category' => ['crypto', 'software']]],
                ['site' => ['domain' => ['example.com']]],
                ['site' => ['category' => ['crypto', 'software']]],
            ],
            [
                [
                    'site' => [
                        'domain' => ['example1.com', 'example2.com'],
                        'category' => ['adult', 'crypto', 'software'],
                    ],
                ],
                ['site' => ['domain' => ['example1.com'], 'category' => ['adult']]],
                ['site' => ['category' => ['crypto', 'software'], 'domain' => ['example2.com']]],
            ],
        ];
    }
}
