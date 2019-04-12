<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\ConfigException;
use Adshares\Adserver\Tests\TestCase;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function factory;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_KEY = 'test-key';

    /** @test */
    public function fetchDateTime(): void
    {
        $dateTime = new DateTime('2000-01-01');

        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => $dateTime->format(DateTime::ATOM),
        ]);

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY));
    }

    /** @test */
    public function fetchDateTime_notInDatabase(): void
    {
        self::assertEquals(new DateTime('@0'), Config::fetchDateTime(self::TEST_KEY));

        $dateTime = new DateTime('2000-01-01');

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY, $dateTime));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY, $dateTime));
    }

    /** @test */
    public function upsertDateTime(): void
    {
        $dateTime = new DateTime('2000-01-01');

        Config::upsertDateTime(self::TEST_KEY, $dateTime);

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY));
    }

    /** @test */
    public function fetchInt(): void
    {
        $value = 5;

        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => $value,
        ]);

        self::assertSame($value, Config::fetchInt(self::TEST_KEY));
    }

    /** @test */
    public function fetchFloatOrFail(): void
    {
        $value = 5.5;

        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => $value,
        ]);

        self::assertSame($value, Config::fetchFloatOrFail(self::TEST_KEY));
    }

    /** @test */
    public function fetchFloatOrFail_notInDatabase(): void
    {
        $this->expectException(ConfigException::class);

        Config::fetchFloatOrFail(self::TEST_KEY);
    }

    /** @test */
    public function upsertInt(): void
    {
        $value = 9;

        Config::upsertInt(self::TEST_KEY, $value);

        self::assertSame($value, Config::fetchInt(self::TEST_KEY));
    }

    /** @test */
    public function fetchStringOrFail(): void
    {
        $value = 'test-string';

        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => $value,
        ]);

        self::assertSame($value, Config::fetchStringOrFail(self::TEST_KEY));
    }

    /** @test */
    public function fetchStringOrFail_notInDatabase(): void
    {
        $this->expectException(ConfigException::class);

        Config::fetchStringOrFail(self::TEST_KEY);
    }

    /**
     * @test
     * @dataProvider boolDataProvider
     */
    public function isTrueOnly(string $value, bool $result): void
    {
        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => $value,
        ]);

        self::assertSame($result, Config::isTrueOnly(self::TEST_KEY));
    }

    /**
     * @test
     */
    public function isTrueOnly_notInDatabase(): void
    {
        self::assertFalse(Config::isTrueOnly(self::TEST_KEY));
    }

    /** @test */
    public function fetchAdminSettings(): void
    {
        $this->fail();
    }

    /** @test */
    public function updateAdminSettings(): void
    {
        $this->fail();
    }

    public function boolDataProvider(): array
    {
        return [
            ['1', true],
            ['0', false],
            ['', false],
            ['xxx', false],
            ['false', false],
            ['true', false],
            ['123', false],
        ];
    }
}
