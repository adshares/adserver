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

    public function testFetchWithInvalidFilter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

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
        $user = User::factory()->create(['id' => 1]);
        Site::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $site = Site::factory()->create(['id' => 1, 'user_id' => $user->id]);

        NetworkCampaign::factory()->create(['id' => 1, 'landing_url' => 'http://example.com']);
        NetworkCampaign::factory()->create(['id' => 2, 'landing_url' => 'http://adshares.net']);
        NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['id' => 2, 'network_campaign_id' => 1]);
        NetworkBanner::factory()->create(['id' => 3, 'network_campaign_id' => 2]);
        Classification::factory()->create(
            ['banner_id' => 1, 'status' => 0, 'site_id' => $site->id, 'user_id' => 1]
        );
        Classification::factory()->create(
            ['banner_id' => 1, 'status' => 1, 'site_id' => $site->id, 'user_id' => 1]
        );
        Classification::factory()->create(
            ['banner_id' => 3, 'status' => 1, 'site_id' => $site->id, 'user_id' => 1]
        );

        $url = urlencode($url);

        $response = $this->getJson(self::CLASSIFICATION_LIST . '/3?landingUrl=' . $url);
        $response->assertStatus(Response::HTTP_OK);
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertCount(1, $items);
        $this->assertEquals('http://adshares.net', $items[0]['landingUrl']);
    }

    public function provideLandingUrl(): array
    {
        return [
            ['http://adshares.net'],
            ['adshares'],
            ['adshares.net'],
        ];
    }
}
