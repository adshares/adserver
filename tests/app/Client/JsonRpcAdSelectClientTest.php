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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\JsonRpcAdSelectClient;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Result;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JsonRpcAdSelectClientTest extends TestCase
{
    use RefreshDatabase;

    public function testFindBannersOrder()
    {
        $ZONE_UUID_SINGLE_BILLBOARD = '01';
        $ZONE_UUID_DOUBLE_BILLBOARD = '02';
        $ZONE_UUID_TRIPLE_BILLBOARD = '03';
        
        $BANNER_UUID_SINGLE_BILLBOARD = '10';
        $BANNER_UUID_TRIPLE_BILLBOARD = '12';
        
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $site = factory(Site::class)->create(['user_id' => $user->id]);
        
        $site->zones(
            [
                factory(Zone::class)->create(
                    [
                        'site_id' => $site->id,
                        'size' => [
                            'label' => 'single-billboard',
                        ],
                        'uuid' => $ZONE_UUID_SINGLE_BILLBOARD,
                    ]
                ),
                factory(Zone::class)->create(
                    [
                        'site_id' => $site->id,
                        'size' => [
                            'label' => 'double-billboard',
                        ],
                        'uuid' => $ZONE_UUID_DOUBLE_BILLBOARD,
                    ]
                ),
                factory(Zone::class)->create(
                    [
                        'site_id' => $site->id,
                        'size' => [
                            'label' => 'triple-billboard',
                        ],
                        'uuid' => $ZONE_UUID_TRIPLE_BILLBOARD,
                    ]
                ),
            ]
        );

        factory(NetworkCampaign::class)->create(['id' => 1]);
        factory(NetworkBanner::class)->create(
            [
                'network_campaign_id' => 1,
                'width' => '750',
                'height' => '100',
                'uuid' => $BANNER_UUID_SINGLE_BILLBOARD,
            ]
        );
        factory(NetworkBanner::class)->create(
            [
                'network_campaign_id' => 1,
                'width' => '750',
                'height' => '300',
                'uuid' => $BANNER_UUID_TRIPLE_BILLBOARD,
            ]
        );

        $mockResult = $this->createMock(Result::class);
        $mockResult->expects($this->once())->method('toArray')->willReturn(
            [
                [
                    'banner_id' => $BANNER_UUID_SINGLE_BILLBOARD,
                    'request_id' => 0,
                ],
                [
                    'banner_id' => $BANNER_UUID_TRIPLE_BILLBOARD,
                    'request_id' => 2,
                ],
            ]
        );

        $mockJsonRpc = $this->createMock(JsonRpc::class);
        $mockJsonRpc->expects($this->once())->method('call')->willReturnCallback(
            function () use ($mockResult) {
                return $mockResult;
            }
        );

        /** @var $mockJsonRpc JsonRpc */
        $jsonRpcAdSelectClient = new JsonRpcAdSelectClient($mockJsonRpc);

        $requestedZones = [
            ['zone' => $ZONE_UUID_SINGLE_BILLBOARD],
            ['zone' => $ZONE_UUID_TRIPLE_BILLBOARD],
            ['zone' => $ZONE_UUID_DOUBLE_BILLBOARD],
        ];

        $context = new ImpressionContext([], [], ['keywords' => [], 'tid' => (Uuid::v4())->toString()]);
        $foundBanners = $jsonRpcAdSelectClient->findBanners($requestedZones, $context);

        $this->assertCount(3, $foundBanners);
        
        foreach ($foundBanners as $foundBanner) {
            $this->assertNotEquals(
                $ZONE_UUID_DOUBLE_BILLBOARD,
                $foundBanner['zone_id'],
                'Function findBanner returned banner for invalid zone'
            );
        }

        $this->assertEquals($ZONE_UUID_SINGLE_BILLBOARD, $foundBanners->get(0)['zone_id']);
        $this->assertEquals($BANNER_UUID_SINGLE_BILLBOARD, $foundBanners->get(0)['id']);

        $this->assertEquals($ZONE_UUID_TRIPLE_BILLBOARD, $foundBanners->get(1)['zone_id']);
        $this->assertEquals($BANNER_UUID_TRIPLE_BILLBOARD, $foundBanners->get(1)['id']);
        
        $this->assertNull($foundBanners->get(2));
    }

    public function testFindBannersNonExistentZone()
    {
        $ZONE_UUID_SINGLE_BILLBOARD = '01';

        $BANNER_UUID_SINGLE_BILLBOARD = '10';

        $mockResult = $this->createMock(Result::class);
        $mockResult->expects($this->once())->method('toArray')->willReturn(
            [
                [
                    'banner_id' => $BANNER_UUID_SINGLE_BILLBOARD,
                    'request_id' => 0,
                ],
            ]
        );

        $mockJsonRpc = $this->createMock(JsonRpc::class);
        $mockJsonRpc->expects($this->once())->method('call')->willReturnCallback(
            function () use ($mockResult) {
                return $mockResult;
            }
        );

        /** @var $mockJsonRpc JsonRpc */
        $jsonRpcAdSelectClient = new JsonRpcAdSelectClient($mockJsonRpc);

        $requestedZones = [
            ['zone' => $ZONE_UUID_SINGLE_BILLBOARD],
        ];

        $context = new ImpressionContext([], [], ['keywords' => []]);
        $foundBanners = $jsonRpcAdSelectClient->findBanners($requestedZones, $context);

        $this->assertCount(1, $foundBanners);
        $this->assertNull($foundBanners->get(0));
    }
}
