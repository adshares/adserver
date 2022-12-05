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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Client\GuzzleAdSelectClient;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\ValueObject\Size;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class SupplyControllerTest extends TestCase
{
    private const BANNER_FIND_URI = '/supply/find';
    private const PAGE_WHY_URI = '/supply/why';
    private const SUPPLY_ANON_URI = '/supply/anon';
    private const LEGACY_FOUND_BANNERS_STRUCTURE = [
        'id',
        'publisher_id',
        'zone_id',
        'pay_from',
        'pay_to',
        'type',
        'size',
        'serve_url',
        'creative_sha1',
        'click_url',
        'view_url',
        'rpm',
    ];
    private const FIND_BANNER_STRUCTURE = [
        'placementId',
        'zoneId',
        'publisherId',
        'demandServer',
        'supplyServer',
        'type',
        'scope',
        'hash',
        'serveUrl',
        'viewUrl',
        'clickUrl',
        'rpm',
    ];
    private const DYNAMIC_FIND_BANNER_STRUCTURE = [
        'id',
        ...self::FIND_BANNER_STRUCTURE,
    ];
    private const FOUND_BANNERS_WITH_CREATION_STRUCTURE = [
        'banners' => [
            '*' => self::LEGACY_FOUND_BANNERS_STRUCTURE,
        ],
        'zones',
        'zoneSizes',
        'success',
    ];

    public function testPageWhyNoParameters(): void
    {
        $response = $this->get(self::PAGE_WHY_URI);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPageWhyInvalidBannerId(): void
    {
        $response = $this->get(
            self::PAGE_WHY_URI . '?bid=0123456789abcdef&cid=0123456789abcdef0123456789abcdef'
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPageWhyNonExistentBannerId(): void
    {
        $response = $this->get(
            self::PAGE_WHY_URI . '?bid=0123456789abcdef0123456789abcdef&cid=0123456789abcdef0123456789abcdef'
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPageWhy(): void
    {
        $host = 'https://example.com';
        $campaignId = 1;
        NetworkHost::factory()->create(['host' => $host]);
        NetworkCampaign::factory()->create(['id' => $campaignId, 'source_host' => $host]);
        $banner = NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => $campaignId]);

        $response = $this->get(self::PAGE_WHY_URI . '?bid=' . $banner->uuid . '&cid=0123456789abcdef0123456789abcdef');

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testFind(): void
    {
        $this->mockAdSelect();
        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'status' => Site::STATUS_ACTIVE]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site->id]);
        $data = [
            'page' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
            ],
            'placements' => [
                ['placementId' => $zone->uuid],
            ],
        ];

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => self::FIND_BANNER_STRUCTURE]]);
    }

    public function testFindWithoutPlacements(): void
    {
        $data = [
            'page' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
            ],
        ];

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFindWithExistingUserWhoIsAdvertiserOnly(): void
    {
        $this->mockAdSelect();
        /** @var User $user */
        $user = User::factory()->create([
            'api_token' => '1234',
            'auto_withdrawal' => 1e11,
            'is_publisher' => 0,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E'),
        ]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'status' => Site::STATUS_ACTIVE]);
        Zone::factory()->create(['site_id' => $site->id, 'size' => '300x250']);
        $data = self::getDynamicFindData();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFindDynamicWithExistingUser(): void
    {
        $this->mockAdSelect();
        $data = self::getDynamicFindData();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => self::DYNAMIC_FIND_BANNER_STRUCTURE]]);
    }

    public function testFindDynamicPopup(): void
    {
        $this->mockAdSelect();
        $data = self::getDynamicFindData(['placements' => [
            self::getPlacementData(['types' => [Banner::TEXT_TYPE_DIRECT_LINK]])
        ]]);

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['*' => self::DYNAMIC_FIND_BANNER_STRUCTURE]]);

        self::assertDatabaseHas(Zone::class, ['type' => Size::TYPE_POP]);
    }

    /**
     * @dataProvider findDynamicFailProvider
     */
    public function testFindDynamicFail(array $data): void
    {
        $this->mockAdSelect();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function findDynamicFailProvider(): array
    {
        return [
            'conflicting placement types' => [
                self::getDynamicFindData(['placements' => [
                    self::getPlacementData(['types' => [Banner::TEXT_TYPE_IMAGE, Banner::TEXT_TYPE_DIRECT_LINK]])
                ]])
            ],
        ];
    }

    public function testFindWithExistingUserWhenDefaultUserRoleDoesNotContainPublisher(): void
    {
        Config::updateAdminSettings([
            Config::AUTO_REGISTRATION_ENABLED => '1',
            Config::DEFAULT_USER_ROLES => 'advertiser',
        ]);
        $this->mockAdSelect();
        $data = self::getDynamicFindData();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFindNoData(): void
    {
        $response = self::post(self::BANNER_FIND_URI);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFindJson(): void
    {
        Config::updateAdminSettings([Config::AUTO_REGISTRATION_ENABLED => '1']);
        $this->mockAdSelect();
        $response = self::post(self::SUPPLY_ANON_URI, self::findJsonData());

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FOUND_BANNERS_WITH_CREATION_STRUCTURE);
        self::assertEquals('Decentraland (0, -10)', Site::first()->name);
        self::assertDatabaseHas(
            User::class,
            [
                'auto_withdrawal' => '100000000',
                'wallet_address' => 'ads:0001-00000001-8B4E',
            ]
        );
    }

    public function testFindJsonWhenDefaultUserRoleDoesNotContainPublisher(): void
    {
        Config::updateAdminSettings([
            Config::AUTO_REGISTRATION_ENABLED => '1',
            Config::DEFAULT_USER_ROLES => 'advertiser',
        ]);
        $this->mockAdSelect();
        $response = self::post(self::SUPPLY_ANON_URI, self::findJsonData());

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFindJsonExistingUserIsAdvertiserOnly(): void
    {
        Config::updateAdminSettings([Config::AUTO_REGISTRATION_ENABLED => '1']);
        User::factory()->create([
            'is_publisher' => 0,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E'),
        ]);
        $this->mockAdSelect();
        $response = self::post(self::SUPPLY_ANON_URI, self::findJsonData());
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFindJsonNoAutoRegistration(): void
    {
        Config::updateAdminSettings([Config::AUTO_REGISTRATION_ENABLED => '0']);
        $this->mockAdSelect();
        $response = self::post(self::SUPPLY_ANON_URI, self::findJsonData());
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFindJsonNoData(): void
    {
        $response = self::post(self::SUPPLY_ANON_URI);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function findJsonData(): array
    {
        return [
            'pay_to' => 'ADS:0001-00000001-8B4E',
            'view_id' => '0123456789ABCDEF0123456789ABCDEF',
            'type' => 'image',
            'width' => 300,
            'height' => 250,
            'context' => [
                'user' => ['language' => 'en'],
                'device' => ['os' => 'Windows'],
                'site' => ['url' => 'https://scene-0-n10.decentraland.org/'],
            ],
            'medium' => 'metaverse',
            'vendor' => 'decentraland',
        ];
    }

    private function mockAdSelect(): void
    {
        NetworkCampaign::factory()->create(['id' => 1]);
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create(['network_campaign_id' => 1]);
        $client = self::createMock(Client::class);
        $client->method('post')->willReturnCallback(function ($uri, $options) use ($networkBanner) {
            $requestId = $options[RequestOptions::JSON][0]['request_id'];
            $content = json_encode([$requestId => [['banner_id' => $networkBanner->uuid, 'rpm' => '0.01']]]);
            $response = self::createMock(ResponseInterface::class);
            $response->method('getBody')->willReturn($content);
            return $response;
        });


        $this->app->bind(
            AdSelect::class,
            static function () use ($client) {
                return new GuzzleAdSelectClient($client);
            }
        );
    }

    private static function getDynamicFindData(array $merge = []): array
    {
        return array_merge([
            'page' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
                'publisher' => 'ADS:0001-00000001-8B4E',
                'medium' => 'web',
            ],
            'placements' => [
                self::getPlacementData(),
            ],
        ], $merge);
    }

    private static function getPlacementData(array $merge = []): array
    {
        return array_merge([
            'id' => 'a1',
            'name' => 'test-zone',
            'width' => '300',
            'height' => '250',
        ], $merge);
    }
}
