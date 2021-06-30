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

namespace Adshares\Test\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    /** @dataProvider provider */
    public function test(string $url, string $idn): void
    {
        $object = new Url($url);

        self::assertSame($url, $object->utf8());
        self::assertSame($idn, $object->toString());
    }

    /** @dataProvider failureProvider */
    public function testFailure($url): void
    {
        $this->expectException(RuntimeException::class);

        new Url($url);
    }

    public function provider(): array
    {
        return [
            ['https://adshares.net', 'https://adshares.net'],
            ['https://🍕adshares.net', 'xn--https://adshares-pg68o.net'],
            ['https://a🍕dshares.net', 'xn--https://adshares-qg68o.net'],
            ['https://ads🍕hares.net', 'xn--https://adshares-sg68o.net'],
            ['https://adshares🍕.net', 'xn--https://adshares-xg68o.net'],
            ['https://adshares.🍕net', 'https://adshares.xn--net-o803b'],
            ['https://adshares.n🍕et', 'https://adshares.xn--net-p803b'],
            ['https://adshares.ne🍕t', 'https://adshares.xn--net-q803b'],
            ['https://adshares.net🍕', 'https://adshares.xn--net-r803b'],
        ];
    }

    public function failureProvider()
    {
        return [
            ['AdServer.https%3A%2F%2Fadshares.net'],
            ['https%3A%2F%2Fadshares.net'],
            ['adshares.net'],
        ];
    }

    public function testToString(): void
    {
        $string = 'https://example.com';
        $url = new Url($string);

        self::assertEquals($string, (string)$url);
    }
}
