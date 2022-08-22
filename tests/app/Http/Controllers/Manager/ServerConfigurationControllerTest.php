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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Model\Currency;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

final class ServerConfigurationControllerTest extends TestCase
{
    private const URI_CONFIG = '/api/config';

    public function testAccessAdminNoJwt(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->getJson(self::URI_CONFIG);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserNoJwt(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->getJson(self::URI_CONFIG);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserJwt(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(
            self::URI_CONFIG,
            $this->getHeaders($user)
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFetch(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJson(
            self::URI_CONFIG,
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['support-email', 'technical-email']);
    }

    public function testFetchByKey(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJson(
            self::URI_CONFIG . '/support-email',
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['support-email']);
    }

    public function testFetchByInvalidKey(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJson(
            self::URI_CONFIG . '/invalid',
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchByKeyWhileValueIsNull(): void
    {
        $key = Config::ADSHARES_SECRET;
        Config::updateAdminSettings([$key => null]);
        $admin = User::factory()->admin()->create();

        $response = $this->getJson(
            self::URI_CONFIG . '/' . $key,
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([$key => null]);
    }

    public function testStoreSingle(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->putJson(
            self::URI_CONFIG . '/support-email',
            ['value' => 'sup@example.com'],
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(Config::class, ['value' => 'sup@example.com']);
    }

    public function testStoreSingleNull(): void
    {
        $nullableKey = Config::OPERATOR_RX_FEE;
        $defaultValueOfNullableKey = '0.01';
        $admin = User::factory()->admin()->create();

        $response = $this->putJson(
            self::URI_CONFIG . '/' . $nullableKey,
            ['value' => null],
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseMissing(Config::class, ['key' => $nullableKey]);

        $response = $this->getJson(
            self::URI_CONFIG . '/' . $nullableKey,
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([$nullableKey => $defaultValueOfNullableKey]);
    }

    public function testStore(): void
    {
        $admin = User::factory()->admin()->create();
        $data = [
            Config::ADSHARES_ADDRESS => '0001-00000003-AB0C',
            Config::EXCHANGE_CURRENCIES => 'EUR,USD',
            Config::INVENTORY_EXPORT_WHITELIST => '0001-00000003-AB0C,0001-00000005-CBCA',
            Config::INVOICE_CURRENCIES => 'EUR',
            Config::REGISTRATION_USER_TYPES => 'advertiser',
            Config::SUPPORT_EMAIL => 'sup@example.com',
            Config::TECHNICAL_EMAIL => 'tech@example.com',
        ];

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
        foreach ($data as $key => $value) {
            self::assertDatabaseHas(Config::class, ['key' => $key, 'value' => $value]);
        }
    }

    /**
     * @dataProvider storeInvalidDataProvider
     */
    public function testStoreInvalidData(array $data): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function storeInvalidDataProvider(): array
    {
        return [
            'invalid key' => [['invalid' => 'invalid']],
            'invalid not empty' => [[Config::INVOICE_COMPANY_CITY => '']],
            'invalid value type' => [[Config::SUPPORT_EMAIL => true]],
            'invalid value length' => [[Config::SUPPORT_EMAIL => str_repeat('a', 65536)]],
            'invalid email format' => [[Config::SUPPORT_EMAIL => 'invalid']],
            'invalid account ID' => [[Config::ADSHARES_ADDRESS => 'invalid']],
            'invalid account ID list' => [[Config::INVENTORY_EXPORT_WHITELIST => 'invalid']],
            'invalid app currency' => [[Config::CURRENCY => 'EUR']],
            'invalid boolean format' => [[Config::COLD_WALLET_IS_ACTIVE => '23']],
            'invalid click amount (not integer)' => [[Config::HOT_WALLET_MIN_VALUE => '1234a']],
            'invalid click amount (negative)' => [[Config::HOT_WALLET_MIN_VALUE => '-1']],
            'invalid click amount (out of range)' => [[Config::HOT_WALLET_MIN_VALUE => '3875820600000000001']],
            'invalid positive integer (not integer)' => [[Config::FIAT_DEPOSIT_MAX_AMOUNT => '1234a']],
            'invalid positive integer (negative)' => [[Config::FIAT_DEPOSIT_MAX_AMOUNT => '-1']],
            'invalid classifier setting' => [[Config::SITE_CLASSIFIER_LOCAL_BANNERS => 'invalid']],
            'invalid registration mode' => [[Config::REGISTRATION_MODE => 'invalid']],
            'invalid commission (negative)' => [[Config::OPERATOR_RX_FEE => '-0.1']],
            'invalid commission (out of range)' => [[Config::OPERATOR_RX_FEE => '1.0001']],
            'invalid commission (empty)' => [[Config::OPERATOR_RX_FEE => '']],
            'invalid invoice currency (lowercase)' => [[Config::NOW_PAYMENTS_CURRENCY => 'eur']],
            'invalid invoice currencies (lowercase)' => [[Config::INVOICE_CURRENCIES => 'eur']],
            'invalid invoice currencies (comma on start)' => [[Config::INVOICE_CURRENCIES => ',EUR']],
            'invalid invoice currencies (comma on end)' => [[Config::INVOICE_CURRENCIES => 'EUR,']],
            'invalid invoice currencies (double comma)' => [[Config::INVOICE_CURRENCIES => 'EUR,,USD']],
            'invalid invoice bank accounts (malformed json)' => [[Config::INVOICE_COMPANY_BANK_ACCOUNTS => '{']],
            'invalid hex (not hex)' =>
                [[Config::ADSHARES_SECRET => 'invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalid0']],
            'invalid hex (size)' => [[Config::ADSHARES_SECRET => '012345678']],
            'invalid host' => [[Config::ADSHARES_NODE_HOST => 'invalid..invalid']],
            'invalid port (no a number)' => [[Config::ADSHARES_NODE_PORT => 'invalid']],
            'invalid port (negative)' => [[Config::ADSHARES_NODE_PORT => '-1']],
            'invalid port (out of range)' => [[Config::ADSHARES_NODE_PORT => '100000']],
            'invalid url' => [[Config::EXCHANGE_API_URL => 'invalid']],
            'invalid license key' => [[Config::ADSHARES_LICENSE_KEY => 'invalid']],
            'invalid mailer' => [[Config::MAIL_MAILER => 'invalid']],
            'invalid country' => [[Config::INVOICE_COMPANY_COUNTRY => 'invalid']],
            'invalid registration user type (empty)' => [[Config::REGISTRATION_USER_TYPES => '']],
            'invalid registration user type (invalid)' => [[Config::REGISTRATION_USER_TYPES => 'invalid']],
        ];
    }

    public function testStoreAppCurrencyWhileUserLedgerEntryIsEmpty(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->patchJson(
            self::URI_CONFIG,
            [Config::CURRENCY => Currency::USD->value],
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testStoreAppCurrencyWhileUserLedgerEntryIsNotEmpty(): void
    {
        $admin = User::factory()->admin()->create();
        UserLedgerEntry::factory()->create();

        $response = $this->patchJson(
            self::URI_CONFIG,
            [Config::CURRENCY => Currency::USD->value],
            $this->getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function getHeaders($user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }
}
