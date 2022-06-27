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

use Adshares\Adserver\Mail\UserBanned;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\PanelPlaceholder;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

final class AdminControllerTest extends TestCase
{
    private const URI_TERMS = '/admin/terms';

    private const URI_PRIVACY_POLICY = '/admin/privacy';

    private const URI_SETTINGS = '/admin/settings';

    private const URI_SITE_SETTINGS = '/admin/site-settings';

    private const URI_REJECTED_DOMAINS = '/admin/rejected-domains';

    private const URI_WALLET = '/admin/wallet';

    private const REGULATION_RESPONSE_STRUCTURE = [
        'content',
    ];

    private const REJECTED_DOMAINS_STRUCTURE = [
        'domains',
    ];

    public function testTermsGetWhileEmpty(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_TERMS);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testTermsGet(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_TERMS, 'old content'));
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_TERMS, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testTermsUpdateByUnauthorizedUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 0]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_TERMS, $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPrivacyPolicyGetWhileEmpty(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_PRIVACY_POLICY);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPrivacyPolicyGet(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_PRIVACY_POLICY, 'old content'));
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_PRIVACY_POLICY, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testPrivacyPolicyUpdateByUnauthorizedUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 0]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_PRIVACY_POLICY, $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSettingsStructureUnauthorized(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 0]), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSettingsStructure(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'settings' => $this->settings(),
        ]);
    }

    public function testSettingsModification(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

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
                    'autoRegistrationEnabled' => 0,
                    'emailVerificationRequired' => 0,
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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

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
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->putJson(self::URI_REJECTED_DOMAINS, ['domains' => $inputDomains]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $databaseDomains = SitesRejectedDomain::all();
        self::assertCount(2, $databaseDomains);
        self::assertEquals($inputDomains, $databaseDomains->pluck('domain')->all());
        $deletedDomains = SitesRejectedDomain::onlyTrashed()->get();
        self::assertCount(1, $deletedDomains);
        self::assertEquals('example1.com', $deletedDomains->first()->domain);
    }

    public function testSiteSettings(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->patch(
            self::URI_SITE_SETTINGS,
            [
                'classifierLocalBanners' => 'all-by-default',
                'acceptBannersManually' => '1',
            ]
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testSiteSettingsClassifierLocalBannersInvalid(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->patch(
            self::URI_SITE_SETTINGS,
            [
                'classifierLocalBanners' => '999',
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user->id, 'status' => Campaign::STATUS_ACTIVE]);
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaign->id, 'status' => Banner::STATUS_ACTIVE]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $response = $this->post(self::buildUriBan($user->id), ['reason' => 'suspicious activity']);

        $response->assertStatus(Response::HTTP_OK);
        self::assertNull(User::find($user->id)->api_token);
        self::assertNull(User::find($user->id)->auto_withdrawal);
        self::assertEquals(Campaign::STATUS_INACTIVE, (new Campaign())->find($campaign->id)->status);
        self::assertEquals(Banner::STATUS_INACTIVE, (new Banner())->find($banner->id)->status);
        self::assertEquals(Site::STATUS_INACTIVE, (new Site())->find($site->id)->status);
        self::assertEquals(Site::STATUS_INACTIVE, (new Site())->find($site->id)->status);
        Mail::assertQueued(UserBanned::class);
    }

    public function testBanAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        $userId = User::factory()->create(['is_admin' => 1])->id;

        $response = $this->post(self::buildUriBan($userId), ['reason' => 'suspicious activity']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanNotExistingUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->post(self::buildUriBan(-1), ['reason' => 'suspicious activity']);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testBanUserByRegularUser(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testBanUserNoReason(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserEmptyReason(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId), ['reason' => ' ']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserTooLongReason(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId), ['reason' => str_repeat('a', 256)]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserDbException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post(self::buildUriBan($user->id), ['reason' => 'suspicious activity']);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testUnbanUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriUnban($userId));

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testUnbanNotExistingUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->post(self::buildUriUnban(-1));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testUnbanUserByRegularUser(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriUnban($userId));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        /** @var User $user */
        $user = User::factory()->create([
            'api_token' => '1234',
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E'),
        ]);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user->id, 'status' => Campaign::STATUS_ACTIVE]);
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaign->id, 'status' => Banner::STATUS_ACTIVE]);
        $banner->classifications()->save(BannerClassification::prepare('test_classifier'));
        /** @var ConversionDefinition $conversionDefinition */
        $conversionDefinition = Conversiondefinition::factory()->create(
            [
                'campaign_id' => $campaign->id,
                'limit_type' => 'in_budget',
                'is_repeatable' => true,
            ]
        );

        /** @var BidStrategy $bidStrategy */
        $bidStrategy = BidStrategy::factory()->create(['user_id' => $user->id]);
        $bidStrategyDetail = BidStrategyDetail::create('user:country:other', 0.2);
        $bidStrategy->bidStrategyDetails()->saveMany([$bidStrategyDetail]);

        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site->id]);

        RefLink::factory()->create(['user_id' => $user->id]);
        Token::generate(Token::PASSWORD_CHANGE, $user, ['password' => 'qwerty123']);

        /** @var NetworkCampaign $networkCampaign */
        $networkCampaign = NetworkCampaign::factory()->create();
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create(
            ['network_campaign_id' => $networkCampaign->id]
        );
        Classification::factory()->create(
            [
                'banner_id' => $networkBanner->id,
                'status' => Classification::STATUS_REJECTED,
                'site_id' => $site->id,
                'user_id' => $user->id,
            ]
        );

        $response = $this->post(self::buildUriDelete($user->id));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertNotEmpty(User::withTrashed()->find($user->id)->deleted_at);
        self::assertNull(User::withTrashed()->find($user->id)->api_token);
        self::assertEmpty(User::withTrashed()->where('email', $user->email)->get());
        self::assertEmpty(User::withTrashed()->where('wallet_address', $user->wallet_address)->get());
        self::assertEmpty(UserSettings::where('user_id', $user->id)->get());
        self::assertNotEmpty(Campaign::withTrashed()->find($campaign->id)->deleted_at);
        self::assertNotEmpty(Banner::withTrashed()->find($banner->id)->deleted_at);
        self::assertEmpty(BannerClassification::all());
        self::assertNotEmpty(ConversionDefinition::withTrashed()->find($conversionDefinition->id)->deleted_at);
        self::assertNotEmpty(BidStrategy::withTrashed()->find($bidStrategy->id)->deleted_at);
        self::assertNotEmpty(BidStrategyDetail::withTrashed()->find($bidStrategyDetail->id)->deleted_at);
        self::assertNotEmpty(Site::withTrashed()->find($site->id)->deleted_at);
        self::assertNotEmpty(Zone::withTrashed()->find($zone->id)->deleted_at);
        self::assertEmpty(RefLink::where('user_id', $user->id)->get());
        self::assertEmpty(Token::where('user_id', $user->id)->get());
        self::assertEmpty(Classification::where('user_id', $user->id)->get());
    }

    public function testDeleteAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        $userId = User::factory()->create(['is_admin' => 1])->id;

        $response = $this->post(self::buildUriDelete($userId));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteNotExistingUser(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');

        $response = $this->post(self::buildUriDelete(-1));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteUserByRegularUser(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriDelete($userId));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteUserDbException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post(self::buildUriDelete($user->id));

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testWallet(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 1]), 'api');
        UserLedgerEntry::factory()->create([
            'amount' => 2000,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_DEPOSIT,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => -2,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_AD_EXPENSE,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => 500,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => -30,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_BONUS_EXPENSE,
        ]);

        $response = $this->get(self::URI_WALLET);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(
            [
                'wallet' => [
                    'balance',
                    'unusedBonuses',
                ]
            ]
        );
        $content = json_decode($response->content(), true);
        self::assertEquals(2468, $content['wallet']['balance']);
        self::assertEquals(470, $content['wallet']['unusedBonuses']);
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

    private static function buildUriBan($userId): string
    {
        return sprintf('/admin/users/%d/ban', $userId);
    }

    private static function buildUriUnban($userId): string
    {
        return sprintf('/admin/users/%d/unban', $userId);
    }

    private static function buildUriDelete($userId): string
    {
        return sprintf('/admin/users/%d/delete', $userId);
    }
}
