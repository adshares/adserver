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
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\License;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\RuntimeException;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

final class AdminControllerTest extends TestCase
{
    private const URI_LICENSE = '/admin/license';
    private const URI_SETTINGS = '/admin/settings';
    private const SETTINGS_STRUCTURE = [
        'settings' => ['adUserInfoUrl']
    ];

    public function testSettingsStructureUnauthorized(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSettingsStructure(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::SETTINGS_STRUCTURE);
    }

    public function testBanUser(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
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
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $userId = User::factory()->admin()->create()->id;

        $response = $this->post(self::buildUriBan($userId), ['reason' => 'suspicious activity']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanNotExistingUser(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

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
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserEmptyReason(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId), ['reason' => ' ']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserTooLongReason(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriBan($userId), ['reason' => str_repeat('a', 256)]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserDbException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->actingAs(User::factory()->admin()->create(), 'api');
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post(self::buildUriBan($user->id), ['reason' => 'suspicious activity']);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testUnbanUser(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $userId = User::factory()->create()->id;

        $response = $this->post(self::buildUriUnban($userId));

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testUnbanNotExistingUser(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

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
        $this->actingAs(User::factory()->admin()->create(), 'api');
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
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $userId = User::factory()->admin()->create()->id;

        $response = $this->post(self::buildUriDelete($userId));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteNotExistingUser(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

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
        $this->actingAs(User::factory()->admin()->create(), 'api');
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post(self::buildUriDelete($user->id));

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testGetLicenseSuccess(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::once())->method('read')->willReturn(
            new License(
                'COM-aBcD02',
                'COM',
                1,
                new DateTimeImmutable('@1658764323'),
                new DateTimeImmutable('@1690300323'),
                'AdServer',
                new AccountId('0001-00000024-FF89'),
                new Commission(0.0),
                new Commission(0.01),
                new Commission(0.02),
                true
            )
        );
        $this->app->bind(LicenseVault::class, fn() => $licenseVault);

        $response = $this->get(self::URI_LICENSE);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => 'COM-aBcD02']);
    }

    public function testGetLicenseFail(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->get(self::URI_LICENSE);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testGrantAdvertiserRights(): void
    {
        $this->login(User::factory()->admin()->create());

        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 0]);

        $response = $this->post(self::buildUriUserRights($user->id, 'grantAdvertising'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['isAdvertiser' => 1]);
        self::assertTrue(User::find($user->id)->isAdvertiser());
    }

    public function testDenyAdvertiserRights(): void
    {
        $this->login(User::factory()->admin()->create());

        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 1]);

        $response = $this->post(self::buildUriUserRights($user->id, 'denyAdvertising'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['isAdvertiser' => 0]);
        self::assertFalse(User::find($user->id)->isAdvertiser());
    }

    public function testGrantPublishingRights(): void
    {
        $this->login(User::factory()->create(['is_moderator' => true]));

        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 0]);

        $response = $this->post(self::buildUriUserRights($user->id, 'grantPublishing'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['isPublisher' => 1]);
        self::assertTrue(User::find($user->id)->isPublisher());
    }

    public function testDenyPublishingRights(): void
    {
        $this->login(User::factory()->create(['is_moderator' => true]));

        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 1]);

        $response = $this->post(self::buildUriUserRights($user->id, 'denyPublishing'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['isPublisher' => 0]);
        self::assertFalse(User::find($user->id)->isPublisher());
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

    private static function buildUriUserRights(int $userId, string $operation): string
    {
        return sprintf('/admin/users/%d/%s', $userId, $operation);
    }
}
