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
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Application\Service\AdSelect;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class SupplyControllerTest extends TestCase
{
    private const BANNER_FIND_URI = '/supply/find';
    private const PAGE_WHY_URI = '/supply/why';
    private const SUPPLY_ANON_URI = '/supply/anon';

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
        factory(NetworkHost::class)->create(['host' => $host]);
        factory(NetworkCampaign::class)->create(['id' => $campaignId, 'source_host' => $host]);
        $banner = factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => $campaignId]);

        $response = $this->get(self::PAGE_WHY_URI . '?bid=' . $banner->uuid . '&cid=0123456789abcdef0123456789abcdef');

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testFind(): void
    {
        factory(NetworkCampaign::class)->create(['id' => 1]);
        /** @var NetworkBanner $networkBanner */
        $networkBanner = factory(NetworkBanner::class)->create(['network_campaign_id' => 1]);
        $adSelectResponse = self::createMock(ResponseInterface::class);
        $adSelectResponse->method('getBody')
            ->willReturn(json_encode([[['banner_id' => $networkBanner->uuid, 'rpm' => '0.01']]]));
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$adSelectResponse]))]);
        $this->app->bind(
            AdSelect::class,
            static function () use ($client) {
                return new GuzzleAdSelectClient($client);
            }
        );
        /** @var User $user */
        $user = factory(User::class)->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Site $site */
        $site = factory(Site::class)->create(['user_id' => $user->id, 'status' => Site::STATUS_ACTIVE]);
        /** @var Zone $zone */
        $zone = factory(Zone::class)->create(['site_id' => $site->id]);
        $data = [
            'page' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
            ],
            'zones' => [
                ['zone' => $zone->uuid]
            ],
        ];
        $content = Utils::urlSafeBase64Encode(json_encode($data));

        $response = self::call('POST', self::BANNER_FIND_URI, [], [], [], [], $content);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([[
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
        ]]);
    }

    public function testFindNoData(): void
    {
        $response = self::post(self::BANNER_FIND_URI);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFindJsonNoData(): void
    {
        $response = self::post(self::SUPPLY_ANON_URI);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
