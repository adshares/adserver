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

use Adshares\Adserver\Utilities\SiteValidator;
use Adshares\Mock\ToStringClass;
use PHPUnit\Framework\TestCase;
use stdClass;

class SiteValidatorTest extends TestCase
{
    /**
     * @dataProvider validUrlProvider
     *
     * @param $url
     */
    public function testIsUrlValid($url): void
    {
        self::assertTrue(SiteValidator::isUrlValid($url));
    }

    /**
     * @dataProvider invalidUrlProvider
     *
     * @param $url
     */
    public function testIsUrlInvalid($url): void
    {
        self::assertFalse(SiteValidator::isUrlValid($url));
    }

    /**
     * @dataProvider validDomainProvider
     *
     * @param $domain
     */
    public function testIsDomainValid($domain): void
    {
        self::assertTrue(SiteValidator::isDomainValid($domain));
    }

    /**
     * @dataProvider invalidDomainProvider
     *
     * @param $domain
     */
    public function testIsDomainInvalid($domain): void
    {
        self::assertFalse(SiteValidator::isDomainValid($domain));
    }

    public function validUrlProvider(): array
    {
        return [
            ['http://example.com'],
            ['https://example.com'],
        ];
    }

    public function invalidUrlProvider(): array
    {
        return [
            [null],
            [''],
            [new StdClass()],
            [new ToStringClass('')],
            ['//example.com'],
            ['https://example.com/path'],
            ['https://example.com?query=true'],
            ['https://example.com#fragment'],
            ['https://example.com/path?query=true#fragment'],
            ['https://login@example.com'],
            ['https://login:password@example.com'],
            ['https://example.com:8080'],
            ['https://.e.com'],
            ['https://.example.com'],
        ];
    }

    public function validDomainProvider(): array
    {
        return [
            ['com'],
            ['e.com'],
            ['example.com'],
            ['app.example.com'],
            ['www.app.example.com'],
        ];
    }

    public function invalidDomainProvider(): array
    {
        return [
            [null],
            [''],
            [new StdClass()],
            [new ToStringClass('')],
            ['//example.com'],
            ['https://example.com'],
            ['https://example.com/path'],
            ['https://example.com?query=true'],
            ['https://example.com#fragment'],
            ['https://example.com/path?query=true#fragment'],
            ['login@example.com'],
            ['login:password@example.com'],
            ['.e.com'],
            ['.example.com'],
        ];
    }
}
