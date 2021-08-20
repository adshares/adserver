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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\ConfigException;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\RuntimeException;
use DateTime;
use DateTimeInterface;

use function factory;

class ConfigTest extends TestCase
{
    private const TEST_KEY = 'test-key';

    /** @test */
    public function fetchDateTime(): void
    {
        $dateTime = new DateTime('2000-01-01');

        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => $dateTime->format(DateTimeInterface::ATOM),
        ]);

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY));
    }

    /** @test */
    public function fetchDateTimeInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);

        factory(Config::class)->create([
            'key' => self::TEST_KEY,
            'value' => 'invalid-date-format',
        ]);

        Config::fetchDateTime(self::TEST_KEY);
    }

    /** @test */
    public function fetchDateTimeNotInDatabase(): void
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
    public function fetchFloatOrFailNotInDatabase(): void
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
    public function fetchStringOrFailNotInDatabase(): void
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
    public function isTrueOnlyNotInDatabase(): void
    {
        self::assertFalse(Config::isTrueOnly(self::TEST_KEY));
    }

    /** @test */
    public function fetchAdminSettings(): void
    {
        $adminSettings = [
            'payment-tx-fee' => '0.01',
            'payment-rx-fee' => '0.01',
            'licence-rx-fee' => '0.01',
            'hotwallet-min-value' => '500000000000000',
            'hotwallet-max-value' => '2000000000000000',
            'cold-wallet-address' => '',
            'cold-wallet-is-active' => '0',
            'adserver-name' => 'AdServer',
            'technical-email' => 'mail@example.com',
            'support-email' => 'mail@example.com',
            'referral-refund-enabled' => '0',
            'referral-refund-commission' => '0',
            'registration-mode' => 'public',
            'auto-confirmation-enabled' => '1',
            'invoice-enabled' => '0',
            'invoice-currencies' => 'EUR,USD',
            'invoice-number-format' => 'INV NNNN/MM/YYYY',
            'invoice-company-name' => '',
            'invoice-company-address' => '',
            'invoice-company-postal-code' => '',
            'invoice-company-city' => '',
            'invoice-company-country' => '',
            'invoice-company-vat-id' => '',
            'invoice-company-bank-accounts' => '',
        ];

        self::assertEquals($adminSettings, Config::fetchAdminSettings());
    }

    /** @test */
    public function updateAdminSettings(): void
    {
        $adminSettings = [
            'payment-tx-fee' => '1',
            'payment-rx-fee' => '2',
            'licence-rx-fee' => '3',
            'hotwallet-min-value' => '4',
            'hotwallet-max-value' => '5',
            'cold-wallet-address' => '0000-00000000-XXXX',
            'cold-wallet-is-active' => '1',
            'adserver-name' => 'xxx',
            'technical-email' => 'mail2@example.com',
            'support-email' => 'mail3@example.com',
            'referral-refund-enabled' => '1',
            'referral-refund-commission' => '0.5',
            'registration-mode' => 'private',
            'auto-confirmation-enabled' => '0',
            'invoice-enabled' => '1',
            'invoice-currencies' => 'PLN',
            'invoice-number-format' => 'AAAA/YYYY',
            'invoice-company-name' => 'Foo',
            'invoice-company-address' => 'Mock street',
            'invoice-company-postal-code' => '1212-89',
            'invoice-company-city' => 'FooCity',
            'invoice-company-country' => 'GB',
            'invoice-company-vat-id' => '123123123123',
            'invoice-company-bank-accounts' => '{}',
        ];

        Config::updateAdminSettings($adminSettings);

        self::assertEquals($adminSettings, Config::fetchAdminSettings());
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
