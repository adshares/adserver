<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

class ConfigTest extends TestCase
{
    private const TEST_KEY = 'test-key';

    public function testFetchDateTime(): void
    {
        $dateTime = new DateTime('2000-01-01');

        Config::factory()->create([
            'key' => self::TEST_KEY,
            'value' => $dateTime->format(DateTimeInterface::ATOM),
        ]);

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY));
    }

    public function testFetchDateTimeInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);

        Config::factory()->create([
            'key' => self::TEST_KEY,
            'value' => 'invalid-date-format',
        ]);

        Config::fetchDateTime(self::TEST_KEY);
    }

    public function testFetchDateTimeNotInDatabase(): void
    {
        self::assertEquals(new DateTime('@0'), Config::fetchDateTime(self::TEST_KEY));

        $dateTime = new DateTime('2000-01-01');

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY, $dateTime));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY, $dateTime));
    }

    public function testUpsertDateTime(): void
    {
        $dateTime = new DateTime('2000-01-01');

        Config::upsertDateTime(self::TEST_KEY, $dateTime);

        self::assertEquals($dateTime, Config::fetchDateTime(self::TEST_KEY));
        self::assertNotSame($dateTime, Config::fetchDateTime(self::TEST_KEY));
    }

    public function testFetchInt(): void
    {
        $value = 5;

        Config::factory()->create([
            'key' => self::TEST_KEY,
            'value' => $value,
        ]);

        self::assertSame($value, Config::fetchInt(self::TEST_KEY));
    }

    public function testUpsertInt(): void
    {
        $value = 9;

        Config::upsertInt(self::TEST_KEY, $value);

        self::assertSame($value, Config::fetchInt(self::TEST_KEY));
    }

    /**
     * @dataProvider boolDataProvider
     */
    public function testIsTrueOnly(string $value, bool $result): void
    {
        Config::updateAdminSettings([
            Config::COLD_WALLET_IS_ACTIVE => $value
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        self::assertSame($result, config('app.cold_wallet_is_active'));
    }

    public function testIsTrueOnlyNotInDatabase(): void
    {
        self::assertNotTrue(config('app.test_key'));
    }

    public function testFetchAdminSettings(): void
    {
        $expectedSettings = [
            'payment-tx-fee' => '0.01',
            'payment-rx-fee' => '0.01',
            'hotwallet-min-value' => 1_000_000_000_000_00,
            'hotwallet-max-value' => 10_000_000_000_000_00,
            'cold-wallet-address' => '',
            'cold-wallet-is-active' => false,
            'adserver-name' => 'AdServer',
            'technical-email' => 'mail@example.com',
            'support-email' => 'mail@example.com',
            'referral-refund-enabled' => false,
            'referral-refund-commission' => 0,
            'registration-mode' => 'public',
            'auto-registration-enabled' => true,
            'auto-confirmation-enabled' => true,
            'email-verification-required' => false,
            'invoice-enabled' => false,
            'invoice-currencies' => ['EUR', 'USD'],
            'invoice-number-format' => 'INV NNNN/MM/YYYY',
            'invoice-company-name' => '',
            'invoice-company-address' => '',
            'invoice-company-postal-code' => '',
            'invoice-company-city' => '',
            'invoice-company-country' => '',
            'invoice-company-vat-id' => '',
            'invoice-company-bank-accounts' => '',
            'site-accept-banners-manually' => false,
            'site-classifier-local-banners' => 'all-by-default',
        ];

        Cache::forget('config.admin');

        $settings = Config::fetchAdminSettings();
        foreach ($expectedSettings as $key => $value) {
            self::assertArrayHasKey($key, $settings);
            self::assertEquals($value, $settings[$key], sprintf('For key `%s`', $key));
        }
    }

    public function testUpdateAdminSettings(): void
    {
        $expectedSettings = [
            'payment-tx-fee' => '1',
            'payment-rx-fee' => '2',
            'hotwallet-min-value' => '4',
            'hotwallet-max-value' => '5',
            'cold-wallet-address' => '0000-00000000-XXXX',
            'cold-wallet-is-active' => '1',
            'adserver-name' => 'xxx',
            'technical-email' => 'mail2@example.com',
            'support-email' => 'mail3@example.com',
            'referral-refund-enabled' => '1',
            'referral-refund-commission' => '0.5',
            'registration-mode' => 'public',
            'auto-registration-enabled' => false,
            'auto-confirmation-enabled' => false,
            'email-verification-required' => '1',
            'invoice-enabled' => '1',
            'invoice-currencies' => ['PLN'],
            'invoice-number-format' => 'AAAA/YYYY',
            'invoice-company-name' => 'Foo',
            'invoice-company-address' => 'Mock street',
            'invoice-company-postal-code' => '1212-89',
            'invoice-company-city' => 'FooCity',
            'invoice-company-country' => 'GB',
            'invoice-company-vat-id' => '123123123123',
            'invoice-company-bank-accounts' => ['EUR' => ['number' => '11 2222 333 4444', 'name' => 'test bank']],
            'site-accept-banners-manually' => false,
            'site-classifier-local-banners' => 'all-by-default',
        ];

        $adminSettings = [
            'payment-tx-fee' => '1',
            'payment-rx-fee' => '2',
            'hotwallet-min-value' => '4',
            'hotwallet-max-value' => '5',
            'cold-wallet-address' => '0000-00000000-XXXX',
            'cold-wallet-is-active' => '1',
            'adserver-name' => 'xxx',
            'technical-email' => 'mail2@example.com',
            'support-email' => 'mail3@example.com',
            'referral-refund-enabled' => '1',
            'referral-refund-commission' => '0.5',
            'registration-mode' => 'public',
            'auto-registration-enabled' => '0',
            'auto-confirmation-enabled' => '0',
            'email-verification-required' => '1',
            'invoice-enabled' => '1',
            'invoice-currencies' => 'PLN',
            'invoice-number-format' => 'AAAA/YYYY',
            'invoice-company-name' => 'Foo',
            'invoice-company-address' => 'Mock street',
            'invoice-company-postal-code' => '1212-89',
            'invoice-company-city' => 'FooCity',
            'invoice-company-country' => 'GB',
            'invoice-company-vat-id' => '123123123123',
            'invoice-company-bank-accounts' => '{"EUR":{"number":"11 2222 333 4444","name":"test bank"}}',
            'site-accept-banners-manually' => '0',
            'site-classifier-local-banners' => 'all-by-default',
        ];

        Config::updateAdminSettings($adminSettings);
        Cache::forget('config.admin');

        $settings = Config::fetchAdminSettings();
        foreach ($expectedSettings as $key => $value) {
            self::assertArrayHasKey($key, $settings);
            self::assertEquals($value, $settings[$key], sprintf('For key `%s`', $key));
        }
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
