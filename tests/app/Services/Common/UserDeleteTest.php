<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Services\Common;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Common\Exception\UserDeletionException;
use Adshares\Adserver\Services\Common\UserDelete;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Support\Facades\DB;

final class UserDeleteTest extends TestCase
{
    public function testDeleteUser(): void
    {
        $service = new UserDelete($this->app->make(CampaignRepository::class));
        /** @var User $user */
        $user = User::factory()->create([
            'api_token' => '1234',
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E'),
        ]);
        $email = $user->email;
        $walletAddress = $user->wallet_address;

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

        $service->deleteUser($user);

        self::assertNotEmpty(User::withTrashed()->find($user->id)->deleted_at);
        self::assertNull(User::withTrashed()->find($user->id)->api_token);
        self::assertEmpty(User::withTrashed()->where('email', $email)->get());
        self::assertEmpty(User::withTrashed()->where('wallet_address', $walletAddress)->get());
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

    public function testDeleteUserWhileDatabaseException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $service = new UserDelete($this->app->make(CampaignRepository::class));
        $user = User::factory()->create();

        self::expectException(UserDeletionException::class);

        $service->deleteUser($user);
    }
}
