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
use Adshares\Adserver\Models\PanelPlaceholder;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Model\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

final class ServerConfigurationControllerTest extends TestCase
{
    private const URI_CONFIG = '/api/v2/config';
    private const URI_PLACEHOLDERS = '/api/v2/config/placeholders';

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
        $response = $this->getJson(
            self::URI_CONFIG,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['supportEmail', 'technicalEmail']);
    }

    public function testFetchByKey(): void
    {
        $response = $this->getJson(
            self::URI_CONFIG . '/supportEmail',
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['supportEmail']);
    }

    public function testFetchByInvalidKey(): void
    {
        $response = $this->getJson(
            self::URI_CONFIG . '/invalid',
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchByKeyWhileValueIsNull(): void
    {
        $key = 'adsharesSecret';
        Config::updateAdminSettings([Config::ADSHARES_SECRET => null]);

        $response = $this->getJson(
            self::URI_CONFIG . '/' . $key,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([$key => null]);
    }

    public function testStoreSingle(): void
    {
        $response = $this->putJson(
            self::URI_CONFIG . '/supportEmail',
            ['value' => 'sup@example.com'],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['supportEmail' => 'sup@example.com']);
        self::assertDatabaseHas(Config::class, [
            'key' => Config::SUPPORT_EMAIL,
            'value' => 'sup@example.com',
        ]);
    }

    public function testStoreError(): void
    {
        DB::shouldReceive('beginTransaction')->andThrow(new PDOException('test exception'));

        $response = $this->putJson(
            self::URI_CONFIG . '/supportEmail',
            ['value' => 'sup@example.com'],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testStoreNothing(): void
    {
        $response = $this->patchJson(
            self::URI_CONFIG,
            [],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testStoreSingleNull(): void
    {
        $nullableKey = 'paymentRxFee';
        $defaultValueOfNullableKey = '0.01';

        $response = $this->putJson(
            self::URI_CONFIG . '/' . $nullableKey,
            ['value' => null],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([$nullableKey => $defaultValueOfNullableKey]);
        self::assertDatabaseMissing(Config::class, ['key' => Config::OPERATOR_RX_FEE]);

        $response = $this->getJson(
            self::URI_CONFIG . '/' . $nullableKey,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([$nullableKey => $defaultValueOfNullableKey]);
    }

    /**
     * @dataProvider storeDataProvider
     */
    public function testStore(string $key, string $value): void
    {
        $data = [Str::camel($key) => $value];

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson($data);
        self::assertDatabaseHas(Config::class, ['key' => $key, 'value' => $value]);
    }

    public function storeDataProvider(): array
    {
        return [
            'ADSHARES_ADDRESS' => [Config::ADSHARES_ADDRESS, '0001-00000003-AB0C'],
            'HOURS_UNTIL_INACTIVE_HOST_REMOVAL' => [Config::HOURS_UNTIL_INACTIVE_HOST_REMOVAL, '168'],
            'INVENTORY_FAILED_CONNECTION_LIMIT' => [Config::INVENTORY_FAILED_CONNECTION_LIMIT, '8'],
            'REFERRAL_REFUND_COMMISSION' => [Config::REFERRAL_REFUND_COMMISSION, '0'],
            'REFERRAL_REFUND_ENABLED' => [Config::REFERRAL_REFUND_ENABLED, '1'],
            'SUPPORT_EMAIL' => [Config::SUPPORT_EMAIL, 'sup@example.com'],
            'TECHNICAL_EMAIL' => [Config::TECHNICAL_EMAIL, 'tech@example.com'],
        ];
    }

    /**
     * @dataProvider storeArrayDataProvider
     */
    public function testStoreArray(string $key, string $value): void
    {
        $data = [Str::camel($key) => $value];

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([Str::camel($key) => explode(',', $value)]);
        self::assertDatabaseHas(Config::class, ['key' => $key, 'value' => $value]);
    }

    public function storeArrayDataProvider(): array
    {
        return [
            'DEFAULT_USER_ROLES' => [Config::DEFAULT_USER_ROLES, 'advertiser'],
            'EXCHANGE_CURRENCIES' => [Config::EXCHANGE_CURRENCIES, 'EUR,USD'],
            'INVENTORY_EXPORT_WHITELIST' =>
                [Config::INVENTORY_EXPORT_WHITELIST, '0001-00000003-AB0C,0001-00000005-CBCA'],
            'INVOICE_CURRENCIES' => [Config::INVOICE_CURRENCIES, 'EUR'],
        ];
    }

    public function testStoreRejectedDomains(): void
    {
        $data = ['rejectedDomains' => 'example.com'];

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['rejectedDomains' => ['example.com']]);
        self::assertDatabaseHas(SitesRejectedDomain::class, ['domain' => 'example.com']);
    }

    public function testStoreRejectedDomainsEmpty(): void
    {
        SitesRejectedDomain::factory()->create(['domain' => 'rejected.com']);
        $data = ['rejectedDomains' => ''];

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertEmpty(SitesRejectedDomain::all());
    }

    /**
     * @dataProvider storeInvalidDataProvider
     */
    public function testStoreInvalidData(array $data): void
    {
        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function storeInvalidDataProvider(): array
    {
        $dataSet = [
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
            'invalid user role (empty)' => [[Config::DEFAULT_USER_ROLES => '']],
            'invalid user role (invalid)' => [[Config::DEFAULT_USER_ROLES => 'invalid']],
            'invalid rejected domains' => [['rejected-domains' => 'a,b']],
            'invalid inventory failed connection limit' => [[Config::INVENTORY_FAILED_CONNECTION_LIMIT => '0']],
            'invalid inactive host removal period' => [[Config::HOURS_UNTIL_INACTIVE_HOST_REMOVAL => '0']],
        ];

        foreach ($dataSet as &$data) {
            $configKey = array_key_first($data[0]);
            $data = [[Str::camel($configKey) => $data[0][$configKey]]];
        }
        return $dataSet;
    }

    public function testStoreAppCurrencyWhileUserLedgerEntryIsEmpty(): void
    {
        $response = $this->patchJson(
            self::URI_CONFIG,
            ['currency' => Currency::USD->value],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testStoreAppCurrencyWhileUserLedgerEntryIsNotEmpty(): void
    {
        UserLedgerEntry::factory()->create();

        $response = $this->patchJson(
            self::URI_CONFIG,
            ['currency' => Currency::USD->value],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchPlaceholders(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_INDEX_KEYWORDS, 'ads'));
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_INDEX_TITLE, 'title'));

        $response = $this->getJson(
            self::URI_PLACEHOLDERS,
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([
            'indexDescription' => null,
            'indexKeywords' => 'ads',
            'indexMetaTags' => null,
            'indexTitle' => 'title',
            'loginInfo' => null,
            'robotsTxt' => null,
            'privacyPolicy' => null,
            'terms' => null,
        ]);
    }

    public function testFetchPlaceholdersByKeyWhilePresent(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_INDEX_TITLE, 'title'));

        $response = $this->getJson(
            self::URI_PLACEHOLDERS . '/indexTitle',
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['indexTitle' => 'title']);
    }

    public function testFetchPlaceholdersByKeyWhileMissing(): void
    {
        $response = $this->getJson(
            self::URI_PLACEHOLDERS . '/indexTitle',
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['indexTitle' => null]);
    }

    public function testFetchPlaceholdersByInvalidKey(): void
    {
        $response = $this->getJson(
            self::URI_PLACEHOLDERS . '/invalid',
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testStorePlaceholders(): void
    {
        $response = $this->patchJson(
            self::URI_PLACEHOLDERS,
            ['indexTitle' => 'title'],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(PanelPlaceholder::class, [
            PanelPlaceholder::FIELD_CONTENT => 'title',
            PanelPlaceholder::FIELD_TYPE => PanelPlaceholder::TYPE_INDEX_TITLE,
        ]);
    }

    public function testStorePlaceholdersDbError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();

        $response = $this->patchJson(
            self::URI_PLACEHOLDERS,
            ['indexTitle' => 'title'],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testStorePlaceholdersInvalidKey(): void
    {
        $response = $this->patchJson(
            self::URI_PLACEHOLDERS,
            ['invalid' => 'title'],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertDatabaseMissing(PanelPlaceholder::class, [
            PanelPlaceholder::FIELD_CONTENT => 'title',
            PanelPlaceholder::FIELD_TYPE => PanelPlaceholder::TYPE_INDEX_TITLE,
        ]);
    }

    public function testStorePlaceholdersInvalidValue(): void
    {
        $response = $this->patchJson(
            self::URI_PLACEHOLDERS,
            ['indexTitle' => true],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertDatabaseMissing(PanelPlaceholder::class, [
            PanelPlaceholder::FIELD_TYPE => PanelPlaceholder::TYPE_INDEX_TITLE,
        ]);
    }

    public function testStorePlaceholdersNothing(): void
    {
        $response = $this->patchJson(
            self::URI_PLACEHOLDERS,
            [],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testStorePlaceholdersDeleting(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_LOGIN_INFO, '<div>Hello</div>'));

        $response = $this->patchJson(
            self::URI_PLACEHOLDERS,
            ['loginInfo' => null],
            $this->getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([
            'loginInfo' => null,
        ]);
        self::assertEmpty(PanelPlaceholder::fetchByTypes([PanelPlaceholder::TYPE_LOGIN_INFO]));
    }

    private function getHeaders($user = null): array
    {
        if (null === $user) {
            $user = User::factory()->admin()->create();
        }
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }
}
