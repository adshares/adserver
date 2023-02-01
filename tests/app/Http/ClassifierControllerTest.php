<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

final class ClassifierControllerTest extends TestCase
{
    private const CLASSIFICATION_LIST = '/api/classifications';

    public function testFetch(): void
    {
        $user = $this->login();
        /** @var Site $site */
        $site = Site::factory()->create(['medium' => 'metaverse', 'user_id' => $user, 'vendor' => 'decentraland']);
        $campaignDecentraland = NetworkCampaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'decentraland']);
        /** @var NetworkBanner $bannerDecentraland */
        $bannerDecentraland = NetworkBanner::factory()->create(['network_campaign_id' => $campaignDecentraland]);
        $campaignMetaverse = NetworkCampaign::factory()->create(['medium' => 'metaverse', 'vendor' => null]);
        /** @var NetworkBanner $bannerMetaverse */
        $bannerMetaverse = NetworkBanner::factory()->create(['network_campaign_id' => $campaignMetaverse]);
        $campaignWeb = NetworkCampaign::factory()->create(['medium' => 'web', 'vendor' => null]);
        NetworkBanner::factory()->create(['network_campaign_id' => $campaignWeb]);
        $expectedBannerIds = [$bannerDecentraland->id, $bannerMetaverse->id];

        $response = $this->getJson(sprintf('%s/%d', self::CLASSIFICATION_LIST, $site->id));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('itemsCount', 2);
        self::assertEqualsCanonicalizing($expectedBannerIds, $response->json('items.*.bannerId'));
    }

    public function testFetchWithFilterBySize(): void
    {
        $user = $this->login();
        /** @var Site $site */
        $site = Site::factory()->create(['medium' => 'metaverse', 'user_id' => $user, 'vendor' => 'decentraland']);
        $campaignDecentraland = NetworkCampaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'decentraland']);
        /** @var NetworkBanner $bannerDecentraland */
        $bannerDecentraland = NetworkBanner::factory()->create([
            'network_campaign_id' => $campaignDecentraland,
            'size' => '2040x2040',
            'type' => 'video',
        ]);
        NetworkBanner::factory()->create([
            'network_campaign_id' => $campaignDecentraland,
            'size' => '100x100',
            'type' => 'video',
        ]);
        $sizes = urlencode(json_encode(['2048x2048','1024x1024']));
        $expectedBannerIds = [$bannerDecentraland->id];

        $response = $this->getJson(sprintf('%s/%d?sizes=%s', self::CLASSIFICATION_LIST, $site->id, $sizes));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('itemsCount', 1);
        self::assertEqualsCanonicalizing($expectedBannerIds, $response->json('items.*.bannerId'));
    }

    public function testFetchWithInvalidFilter(): void
    {
        $user = $this->login();
        Site::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson(self::CLASSIFICATION_LIST . '?banner_id=1');
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGlobalWhenThereIsNoClassifications(): void
    {
        $user = User::factory()->create();
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkCampaign::factory()->create(['id' => 2]);
        NetworkBanner::factory()->create(['network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['network_campaign_id' => 2]);

        $response = $this->getJson(self::CLASSIFICATION_LIST);
        $response->assertStatus(Response::HTTP_OK);
        $content = json_decode($response->getContent(), true);

        $items = $content['items'];

        $this->assertCount(3, $items);
        $this->assertEquals(3, $content['itemsCount']);
        $this->assertEquals(3, $content['itemsCountAll']);

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[1]['classifiedGlobal']);
        $this->assertNull($items[2]['classifiedGlobal']);

        $this->assertNull($items[0]['classifiedSite']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertNull($items[0]['classifiedSite']);
    }

    public function testGlobalWhenThereIsOnlyGlobalClassification(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['id' => 2, 'network_campaign_id' => 1]);
        Classification::factory()->create(
            ['banner_id' => 1, 'status' => false, 'site_id' => null, 'user_id' => 1]
        );

        $response = $this->getJson(self::CLASSIFICATION_LIST);
        $response->assertStatus(Response::HTTP_OK);
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertFalse($items[1]['classifiedGlobal']);
        $this->assertNull($items[1]['classifiedSite']);
    }

    public function testSiteWhenThereIsOnlySiteClassification(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['id' => 2, 'network_campaign_id' => 1]);
        $site = Site::factory()->create();
        Classification::factory()->create(
            ['banner_id' => 1, 'status' => 0, 'site_id' => $site->id, 'user_id' => 1]
        );

        $response = $this->getJson(self::CLASSIFICATION_LIST . '/' . $site->id);
        $response->assertStatus(Response::HTTP_OK);
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertFalse($items[1]['classifiedSite']);
        $this->assertNull($items[1]['classifiedGlobal']);
    }

    public function testSiteWhenThereIsGlobalAndSiteClassification(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['id' => 2, 'network_campaign_id' => 1]);
        Site::factory()->create(['id' => 3]);
        Classification::factory()->create(['banner_id' => 1, 'status' => 0, 'site_id' => 3, 'user_id' => 1]);
        Classification::factory()->create(['banner_id' => 1, 'status' => 1, 'site_id' => null, 'user_id' => 1]);

        $response = $this->getJson(self::CLASSIFICATION_LIST . '/3');
        $response->assertStatus(Response::HTTP_OK);
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertTrue($items[1]['classifiedGlobal']);
        $this->assertFalse($items[1]['classifiedSite']);
    }

    public function testChangeGlobalStatusWhenDoesNotExistInDb(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => false,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', null)
            ->first();

        $this->assertFalse($classification->status);
    }

    public function testChangeGlobalStatusWhenExistsInDb(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        Classification::factory()->create(['banner_id' => 1, 'status' => 0, 'site_id' => null, 'user_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', null)
            ->first();

        $this->assertTrue($classification->status);
    }

    public function testRejectGloballyWhenForSiteExistsInDb(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        User::factory()->create(['id' => 2]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        Site::factory()->create(['id' => 3]);
        Classification::factory()->create(['banner_id' => 1, 'status' => 1, 'site_id' => 3, 'user_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => false,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        /** @var Collection $classification */
        $classification = Classification::where('banner_id', 1)
            ->where('user_id', 1)
            ->get();

        $this->assertCount(1, $classification);
        $this->assertFalse($classification->first()->status);
    }

    public function testChangeSiteStatusWhenDoesNotExistInDb(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST . '/1', $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertNull(Classification::first());
    }

    public function testChangeSiteStatusWhenExistsInDb(): void
    {
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        Site::factory()->create(['id' => 5]);
        Classification::factory()->create(['banner_id' => 1, 'status' => 0, 'site_id' => 5, 'user_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST . '/5', $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', 5)
            ->first();

        $this->assertTrue($classification->status);
    }

    public function testChangeSiteStatusWithoutBannerId(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $site = Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);

        $data = [
            'classification' => [
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST . '/' . $site->id, $data);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangeSiteStatusWhenBannerNotExistsInDb(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $site = Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        NetworkCampaign::factory()->create(['id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST . '/' . $site->id, $data);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider provideLandingUrl
     *
     * @param string $url
     */
    public function testSiteWhenThereIsGlobalAndSiteClassificationFilteringByLandingUrl(string $url): void
    {
        $user = $this->login();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user]);
        $campaignExample = NetworkCampaign::factory()->create(['id' => 1, 'landing_url' => 'https://example.com']);
        $campaignAdshares = NetworkCampaign::factory()->create(['id' => 2, 'landing_url' => 'https://adshares.net']);
        $b1 = NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => $campaignExample]);
        $b2 = NetworkBanner::factory()->create(['id' => 2, 'network_campaign_id' => $campaignExample]);
        $b3 = NetworkBanner::factory()->create(['id' => 3, 'network_campaign_id' => $campaignAdshares]);
        Classification::factory()->create(['banner_id' => $b1, 'status' => 0, 'site_id' => $site, 'user_id' => $user]);
        Classification::factory()->create(['banner_id' => $b2, 'status' => 1, 'site_id' => $site, 'user_id' => $user]);
        Classification::factory()->create(['banner_id' => $b3, 'status' => 1, 'site_id' => $site, 'user_id' => $user]);
        $url = urlencode($url);

        $response = $this->getJson(sprintf('%s/%d?landingUrl=%s', self::CLASSIFICATION_LIST, $site->id, $url));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.landingUrl', 'https://adshares.net');
    }

    public function provideLandingUrl(): array
    {
        return [
            ['https://adshares.net'],
            ['adshares'],
            ['adshares.net'],
        ];
    }
    public function testFetchInvalidSiteId(): void
    {
        $user = $this->login();
        Site::factory()->create(['user_id' => $user]);

        $response = $this->getJson(sprintf('%s/%d', self::CLASSIFICATION_LIST, 1));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
