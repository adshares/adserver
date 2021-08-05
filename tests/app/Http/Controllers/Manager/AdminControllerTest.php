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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\PanelPlaceholder;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

use function json_decode;

final class AdminControllerTest extends TestCase
{
    private const URI_TERMS = '/admin/terms';

    private const URI_PRIVACY_POLICY = '/admin/privacy';

    private const URI_SETTINGS = '/admin/settings';

    private const URI_REJECTED_DOMAINS = '/admin/rejected-domains';

    private const REGULATION_RESPONSE_STRUCTURE = [
        'content',
    ];

    private const REJECTED_DOMAINS_STRUCTURE = [
        'domains',
    ];

    public function testTermsGetWhileEmpty(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_TERMS);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testTermsGet(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_TERMS, 'old content'));
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_TERMS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::REGULATION_RESPONSE_STRUCTURE);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('content', $decodedResponse);
        $this->assertEquals('old content', $decodedResponse['content']);
    }

    public function testTermsUpdate(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_TERMS, 'old content'));
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_TERMS, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testTermsUpdateByUnauthorizedUser(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 0]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_TERMS, $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPrivacyPolicyGetWhileEmpty(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_PRIVACY_POLICY);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPrivacyPolicyGet(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_PRIVACY_POLICY, 'old content'));
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_PRIVACY_POLICY);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::REGULATION_RESPONSE_STRUCTURE);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('content', $decodedResponse);
        $this->assertEquals('old content', $decodedResponse['content']);
    }

    public function testPrivacyPolicyUpdate(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_PRIVACY_POLICY, 'old content'));
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_PRIVACY_POLICY, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testPrivacyPolicyUpdateByUnauthorizedUser(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 0]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_PRIVACY_POLICY, $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSettingsStructureUnauthorized(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 0]), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSettingsStructure(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'settings' => $this->settings(),
        ]);
    }

    public function testSettingsModification(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $updatedValues = [
            'settings' =>
                [
                    'hotwalletMinValue' => 200,
                    'hotwalletMaxValue' => 500,
                    'coldWalletIsActive' => 1,
                    'coldWalletAddress' => '0000-00000000-XXXX',
                    'adserverName' => 'AdServer2',
                    'technicalEmail' => 'mail@example.com2',
                    'supportEmail' => 'mail@example.com3',
                    'advertiserCommission' => 0.05,
                    'publisherCommission' => 0.06,
                    'referralRefundEnabled' => 1,
                    'referralRefundCommission' => 0.5,
                    'registrationMode' => 'private',
                    'autoConfirmationEnabled' => 0,
                ],
        ];

        $response = $this->putJson(self::URI_SETTINGS, $updatedValues);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson($updatedValues);
    }

    public function testInvalidRegistrationMode(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');
        $settings = $this->settings();
        $settings['registrationMode'] = 'dummy';

        $response = $this->putJson(self::URI_SETTINGS, ['settings' => $settings]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRejectedDomainsGet(): void
    {
        $domains = ['example1.com', 'example2.com'];
        foreach ($domains as $domain) {
            SitesRejectedDomain::upsert($domain);
        }
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_REJECTED_DOMAINS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::REJECTED_DOMAINS_STRUCTURE);

        $responseDomains = $response->json(['domains']);

        self::assertCount(2, $responseDomains);
        self::assertEquals($domains, $responseDomains);
    }

    /**
     * @dataProvider invalidRejectedDomainsProvider
     *
     * @param array $data
     * @param int $expectedStatus
     */
    public function testRejectedDomainsPutInvalid(array $data, int $expectedStatus): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->putJson(self::URI_REJECTED_DOMAINS, $data);
        $response->assertStatus($expectedStatus);
    }

    public function invalidRejectedDomainsProvider(): array
    {
        return [
            [
                [],
                Response::HTTP_BAD_REQUEST,
            ],
            [
                ['domains' => 'example.com'],
                Response::HTTP_BAD_REQUEST,
            ],
            [
                ['domains' => ['']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            [
                ['domains' => [1]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
        ];
    }

    public function testRejectedDomainsPutDbConnectionError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->putJson(self::URI_REJECTED_DOMAINS, ['domains' => []]);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testRejectedDomainsPutValid(): void
    {
        $initDomains = ['example1.com', 'example2.com'];
        $inputDomains = ['example2.com', 'example3.com'];
        foreach ($initDomains as $domain) {
            SitesRejectedDomain::upsert($domain);
        }
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->putJson(self::URI_REJECTED_DOMAINS, ['domains' => $inputDomains]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $databaseDomains = SitesRejectedDomain::all();
        self::assertCount(2, $databaseDomains);
        self::assertEquals($inputDomains, $databaseDomains->pluck('domain')->all());
        $deletedDomains = SitesRejectedDomain::onlyTrashed()->get();
        self::assertCount(1, $deletedDomains);
        self::assertEquals('example1.com', $deletedDomains->first()->domain);
    }

    private function settings(): array
    {
        return [
            'hotwalletMinValue' => 500000000000000,
            'hotwalletMaxValue' => 2000000000000000,
            'coldWalletIsActive' => 0,
            'coldWalletAddress' => '',
            'adserverName' => 'AdServer',
            'technicalEmail' => 'mail@example.com',
            'supportEmail' => 'mail@example.com',
            'advertiserCommission' => 0.01,
            'publisherCommission' => 0.01,
            'referralRefundEnabled' => 0,
            'referralRefundCommission' => 0,
            'registrationMode' => 'public',
            'autoConfirmationEnabled' => 1,
        ];
    }
}
