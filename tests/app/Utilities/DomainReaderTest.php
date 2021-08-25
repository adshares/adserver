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

    public function testDomainRead(): void
    {
        $this->assertEquals('example.com', DomainReader::domain('http://example.com'));
        $this->assertEquals('example.com', DomainReader::domain('https://example.com'));
        $this->assertEquals('example.com', DomainReader::domain('https://www.example.com'));
        $this->assertEquals('example.com', DomainReader::domain('https://example.com:8080/find?a=1'));
    }

    public function testCheckDomain(): void
    {
        $this->assertTrue(DomainReader::checkDomain('http://example.com', 'example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://example.com', 'example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://www.example.com', 'example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://example.com:8080/find?a=1', 'example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://sub1.example.com', 'example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://sub1.sub2.example.com', 'example.com'));

        $this->assertFalse(DomainReader::checkDomain('https://foo.com', 'example.com'));
        $this->assertFalse(DomainReader::checkDomain('https://example.foo.com', 'example.com'));

        $this->assertTrue(DomainReader::checkDomain('https://sub1.example.com', 'sub1.example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://sub2.sub1.example.com', 'sub1.example.com'));
        $this->assertTrue(DomainReader::checkDomain('https://www.sub1.example.com', 'sub1.example.com'));
        $this->assertFalse(DomainReader::checkDomain('https://example.com', 'sub1.example.com'));
        $this->assertFalse(DomainReader::checkDomain('https://www.example.com', 'sub1.example.com'));
        $this->assertFalse(DomainReader::checkDomain('https://sub1.sub2.example.com', 'sub1.example.com'));
    }
}
