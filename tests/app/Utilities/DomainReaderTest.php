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

use Adshares\Adserver\Utilities\DomainReader;
use PHPUnit\Framework\TestCase;

final class DomainReaderTest extends TestCase
{
    /**
     * @dataProvider urlProvider
     */
    public function testDomainRead(string $url, string $expectedDomain): void
    {
        $this->assertEquals($expectedDomain, DomainReader::domain($url));
    }

    public function urlProvider(): array
    {
        return [
            ['https://example.com', 'example.com'],
            ['https://www.example.com', 'example.com'],
            ['https://example.com:8080/find?a=1', 'example.com'],
        ];
    }
}
