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

namespace Adshares\Test\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\Exception\InvalidUuidException;
use Adshares\Common\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    private const VALID = true;
    private const INVALID = false;

    /**
     * @dataProvider dataProviderForCreateString
     */
    public function testCreateFromStringWhenValueIsInvalid(string $value, bool $valid = true): void
    {
        if (!$valid) {
            $this->expectException(InvalidUuidException::class);
        }

        $uuid = Uuid::fromString($value);

        $this->assertEquals($value, (string)$uuid);
    }

    public function testEqualsWhenTwoObjectsAreEqual(): void
    {
        $first = Uuid::fromString('4a27f6a938254573abe47810a0b03748');
        $second = Uuid::fromString('4a27f6a938254573abe47810a0b03748');

        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($first));
        $this->assertEquals($first, $second);
    }

    public function testEqualsWhenTwoObjectsAreNotEqual(): void
    {
        $first = Uuid::v4();
        $second = Uuid::v4();

        $this->assertFalse($first->equals($second));
        $this->assertFalse($second->equals($first));
        $this->assertNotEquals($first, $second);
    }

    public function testCaseIdIfLastTwoCharactersAreAlwaysZero(): void
    {
        $this->assertEquals('00', substr(Uuid::caseId(), -2));
        $this->assertEquals('00', substr(Uuid::caseId(), -2));
        $this->assertEquals('00', substr(Uuid::caseId(), -2));
        $this->assertEquals('00', substr(Uuid::caseId(), -2));
        $this->assertEquals('00', substr(Uuid::caseId(), -2));
    }

    public function dataProviderForCreateString()
    {
        return [
            [uniqid(), self::INVALID],
            ['4a27f6a938254573aze47810a0b03748', self::INVALID], // it's not a HEX value
            ['4a27f6a938254573abe47810a0b03748', self::VALID],
        ];
    }
}
