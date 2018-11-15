<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */
declare(strict_types = 1);

namespace Test\Adshares;

use Adshares\Adserver\Utilities\UniqueIdentifierFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UniqueIdTest extends TestCase
{
    /** @test */
    public function createRandom(): void
    {
        $format = '%x%x%x%x%x%x%x%x-%x%x%x%x-%x%x%x%x-%x%x%x%x-%x%x%x%x%x%x%x%x%x%x%x%x';
        $id = UniqueIdentifierFactory::random();

        self::assertStringMatchesFormat($format, (string)$id);
    }

    /**
     * @test
     * @dataProvider fromStringProvider
     *
     * @param string $string The string representation of a UUID
     */
    public function createFromString(string $string): void
    {
        $uuid = UniqueIdentifierFactory::fromString($string);

        self::assertEquals(strtolower($string), (string)$uuid);
    }

    public function fromStringProvider(): array
    {
        return [
            ['b355a5ac-09cb-45d6-9f75-5dd298b3b862'],
            ['5CE9620E-50C6-4AB0-9445-E159D5AD9D08'],
        ];
    }

    /** @test */
    public function creationFailure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UniqueIdentifierFactory::fromString('invalid uuid');
    }
}

