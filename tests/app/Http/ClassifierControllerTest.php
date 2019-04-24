<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Classify\Application\Service\SignatureVerifierInterface;
use function factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function urldecode;
use function urlencode;

final class ClassifierControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLASSIFICATION_LIST = '/api/classifications';

    public function testGlobalWhenThereIsNoClassifications(): void
    {
        $user = factory(User::class)->create();
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkCampaign::class)->create(['id' => 2]);
        factory(NetworkBanner::class)->create(['network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['network_campaign_id' => 2]);
        factory(Classification::class)->create();

        $response = $this->getJson(self::CLASSIFICATION_LIST);
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
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 2, 'network_campaign_id' => 1]);
        factory(Classification::class)->create(
            ['banner_id' => 1, 'status' => false, 'site_id' => null, 'user_id' => 1]
        );

        $response = $this->getJson(self::CLASSIFICATION_LIST);
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertFalse($items[1]['classifiedGlobal']);
        $this->assertNull($items[1]['classifiedSite']);
    }

    public function testSiteWhenThereIsOnlySiteClassification(): void
    {
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 2, 'network_campaign_id' => 1]);
        factory(Classification::class)->create(['banner_id' => 1, 'status' => 0, 'site_id' => 3, 'user_id' => 1]);

        $response = $this->getJson(self::CLASSIFICATION_LIST.'/3');
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertFalse($items[1]['classifiedSite']);
        $this->assertNull($items[1]['classifiedGlobal']);
    }

    public function testSiteWhenThereIsGlobalAndSiteClassification(): void
    {
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 2, 'network_campaign_id' => 1]);
        factory(Classification::class)->create(['banner_id' => 1, 'status' => 0, 'site_id' => 3, 'user_id' => 1]);
        factory(Classification::class)->create(['banner_id' => 1, 'status' => 1, 'site_id' => null, 'user_id' => 1]);

        $response = $this->getJson(self::CLASSIFICATION_LIST.'/3');
        $content = json_decode($response->getContent(), true);
        $items = $content['items'];

        $this->assertNull($items[0]['classifiedGlobal']);
        $this->assertNull($items[0]['classifiedSite']);
        $this->assertTrue($items[1]['classifiedGlobal']);
        $this->assertFalse($items[1]['classifiedSite']);
    }

    public function testChangeGlobalStatusWhenDoesNotExistInDb(): void
    {
        $this->mockVerifier();
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => false,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST, $data);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', null)
            ->first();

        $this->assertFalse($classification->status);
        $this->assertEquals(204, $response->getStatusCode());
    }

    private function mockVerifier()
    {
        $this->app->bind(
            SignatureVerifierInterface::class,
            function () {
                $signatureVerify = $this->createMock(SignatureVerifierInterface::class);

                $signatureVerify
                    ->method('create')
                    ->willReturn('signature');

                return $signatureVerify;
            }
        );
    }

    public function testChangeGlobalStatusWhenExistsInDb(): void
    {
        $this->mockVerifier();
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);
        factory(Classification::class)->create(['banner_id' => 1, 'status' => 0, 'site_id' => null, 'user_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST, $data);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', null)
            ->first();

        $this->assertTrue($classification->status);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testChangeSiteStatusWhenDoesNotExistInDb(): void
    {
        $this->mockVerifier();
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST.'/1', $data);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', 1)
            ->first();

        $this->assertTrue($classification->status);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testChangeSiteStatusWhenExistsInDb(): void
    {
        $this->mockVerifier();
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);
        factory(Classification::class)->create(['banner_id' => 1, 'status' => 0, 'site_id' => 5, 'user_id' => 1]);

        $data = [
            'classification' => [
                'banner_id' => 1,
                'status' => true,
            ],
        ];

        $response = $this->patchJson(self::CLASSIFICATION_LIST.'/5', $data);

        $classification = Classification::where('banner_id', 1)
            ->where('site_id', 5)
            ->first();

        $this->assertTrue($classification->status);
        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * @dataProvider provideLandingUrl
     *
     * @param string $url
     */
    public function testSiteWhenThereIsGlobalAndSiteClassificationFilteringByLandingUrl(string $url): void
    {
        $user = factory(User::class)->create(['id' => 1]);
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        $site = factory(Site::class)->create(['id' => 1, 'user_id' => $user->id]);

        factory(NetworkCampaign::class)->create(['id' => 1, 'landing_url' => 'http://example.com']);
        factory(NetworkCampaign::class)->create(['id' => 2, 'landing_url' => 'http://adshares.net']);
        factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 2, 'network_campaign_id' => 1]);
        factory(NetworkBanner::class)->create(['id' => 3, 'network_campaign_id' => 2]);
        factory(Classification::class)->create(
            ['banner_id' => 1, 'status' => 0, 'site_id' => $site->id, 'user_id' => 1]
        );
        factory(Classification::class)->create(
            ['banner_id' => 1, 'status' => 1, 'site_id' => $site->id, 'user_id' => 1]
        );
        factory(Classification::class)->create(
            ['banner_id' => 3, 'status' => 1, 'site_id' => $site->id, 'user_id' => 1]
        );

        $url = urlencode($url);

        $response = $this->getJson(self::CLASSIFICATION_LIST.'/3?landingUrl='.$url);
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
