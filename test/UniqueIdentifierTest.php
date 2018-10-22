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
declare(strict_types=1);
namespace Test\AdServer;

use Internals\UniqueId;
use PHPUnit\Framework\TestCase;

class UniqueIdentifierTest extends TestCase
{
    /** @test */
    public function createRandom(): void
    {
        $randomId = UniqueId::random();

        self::assertInstanceOf(UniqueId::class, $randomId);
    }

    /**
     * @test
     * @dataProvider fromStringProvider
     *
     * @param string $string The string representation of a UUID
     */
    public function createFromString(string $string): void
    {
        $uuid = UniqueId::fromString($string);

        self::assertInstanceOf(UniqueId::class, $uuid);
        self::assertEquals(strtolower($string), "$uuid");
    }

    public function fromStringProvider(): array
    {
        return [
            ['b355acac-09cb-45d6-9f75-5dd298b3b862'],
            ['5CE9620E-50C6-4AB0-9445-E159D5AD9D08'],
        ];
    }

    /** @test */
    public function creationFailure(): void
    {
        self::expectException(\InvalidArgumentException::class);

        UniqueId::fromString('invalid uuid');
    }
}

