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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\GuzzleAdSelectClient;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class GuzzleAdSelectClientTest extends TestCase
{
    private const FOUND_BANNER_STRUCTURE = [
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
        'info_box',
        'rpm',
        'request_id',
    ];
    private const URI_BOOST_PAYMENT_EXPORT = '/api/v1/boost-payments';
    private const URI_CASE_EXPORT = '/api/v1/cases';
    private const URI_CASE_CLICK_EXPORT = '/api/v1/clicks';
    private const URI_CASE_PAYMENT_EXPORT = '/api/v1/payments';

    public function testExportInventory(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([new Response()]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);
        $campaigns = new CampaignCollection(self::campaign());

        $guzzleAdSelectClient->exportInventory($campaigns);

        self::assertCount(1, $container);
        $request = $container[0]['request'];
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('https://example.com/api/v1/campaigns', $request->getUri());
        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertContains('application/json', $request->getHeader('Content-Type'));
    }

    public function testExportInventoryFail(): void
    {
        $mock = new MockHandler([new Response(BaseResponse::HTTP_SERVICE_UNAVAILABLE, [], '{"error": "test"}')]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);
        $campaigns = new CampaignCollection(self::campaign());

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->exportInventory($campaigns);
    }

    public function testDeleteFromInventory(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([new Response()]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);
        $campaigns = new CampaignCollection(self::campaign());

        $guzzleAdSelectClient->deleteFromInventory($campaigns);

        self::assertCount(1, $container);
        $request = $container[0]['request'];
        self::assertEquals('DELETE', $request->getMethod());
        self::assertEquals('https://example.com/api/v1/campaigns', $request->getUri());
        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertContains('application/json', $request->getHeader('Content-Type'));
    }

    public function testDeleteFromInventoryFail(): void
    {
        $mock = new MockHandler([new Response(BaseResponse::HTTP_SERVICE_UNAVAILABLE, [], '{"error": "test"}')]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);
        $campaigns = new CampaignCollection(self::campaign());

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->deleteFromInventory($campaigns);
    }

    public function testFindBanners(): void
    {
        [$publisher, $zone] = $this->initZone();
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '1',
                'placementId' => $zone->uuid,
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $mock = new MockHandler(
            [
                new Response(
                    body: json_encode(
                        [
                            '1' => [
                                [
                                    'banner_id' => $banner->uuid,
                                    'rpm' => 0.3,
                                ]
                            ]
                        ]
                    )
                ),
            ]
        );
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners[0];
        self::assertNotNull($foundBanner);
        foreach (self::FOUND_BANNER_STRUCTURE as $key) {
            self::assertArrayHasKey($key, $foundBanner);
        }
        self::assertEquals($banner->uuid, $foundBanner['id']);
        self::assertEquals($publisher->uuid, $foundBanner['publisher_id']);
        self::assertEquals($zone->uuid, $foundBanner['zone_id']);
        self::assertEquals($campaign->source_address, $foundBanner['pay_from']);
        self::assertEquals('0001-00000005-CBCA', $foundBanner['pay_to']);
        self::assertEquals($banner->type, $foundBanner['type']);
        self::assertEquals($banner->size, $foundBanner['size']);
        self::assertEquals($banner->serve_url, $foundBanner['serve_url']);
        self::assertEquals($banner->checksum, $foundBanner['creative_sha1']);
        self::assertStringStartsWith('https://example.com/l/n/click/' . $banner->uuid, $foundBanner['click_url']);
        self::assertStringStartsWith('https://example.com/l/n/view/' . $banner->uuid, $foundBanner['view_url']);
        self::assertTrue($foundBanner['info_box']);
        self::assertEquals(0.3, $foundBanner['rpm']);
        self::assertEquals('1', $foundBanner['request_id']);
    }

    public function testFindBannersForMetaverse(): void
    {
        $publisher = User::factory()->create();
        $site = Site::factory()->create(
            [
                'domain' => 'scene-0-0.decentraland.org',
                'medium' => 'metaverse',
                'url' => 'https://scene-0-0.decentraland.org',
                'user_id' => $publisher,
                'vendor' => 'decentraland',
            ]
        );
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);
        $campaign = NetworkCampaign::factory()->create(
            [
                'medium' => 'metaverse',
                'vendor' => 'decentraland',
            ]
        );
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create(['network_campaign_id' => $campaign]);
        $zones = [
            [
                'id' => '0',
                'placementId' => $zone->uuid,
                'options' => [
                    'banner_type' => ['image'],
                    'banner_mime' => ['image/png'],
                ],
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://scene-0-0.decentraland.org',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler(
            [
                new Response(
                    body: json_encode(
                        [
                            '0' => [
                                [
                                    'banner_id' => $banner->uuid,
                                    'rpm' => 0.3,
                                ]
                            ]
                        ]
                    )
                ),
            ]
        );
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners[0];
        self::assertNotNull($foundBanner);
        self::assertCount(1, $container);
        /** @var Request $request */
        $request = $container[0]['request'];
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('https://example.com/api/v1/find', $request->getUri());
        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertContains('application/json', $request->getHeader('Content-Type'));
        $body = json_decode($request->getBody()->getContents(), true);
        self::assertContains('image', $body[0]['banner_filters']['require']['type'] ?? []);
        self::assertContains('image/png', $body[0]['banner_filters']['require']['mime'] ?? []);
    }

    public function testFindBannersWhileSiteHasPopup(): void
    {
        [$publisher, $zone] = $this->initZone();
        /** @var Site $site */
        $site = $zone->site;
        /** @var Zone $popupZone */
        $popupZone = Zone::factory()->create(
            [
                'scopes' => ['pop-up'],
                'site_id' => $site,
                'size' => 'pop-up',
                'type' => 'pop',
            ]
        );
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '0',
                'placementId' => $zone->uuid,
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler(
            [
                new Response(
                    body: json_encode(
                        [
                            '0' => [
                                [
                                    'banner_id' => $banner->uuid,
                                    'rpm' => 0.3,
                                ]
                            ]
                        ]
                    )
                ),
            ]
        );
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
        self::assertCount(2, $foundBanners);
        $foundBanner = $foundBanners[0];
        self::assertNotNull($foundBanner);
        foreach (self::FOUND_BANNER_STRUCTURE as $key) {
            self::assertArrayHasKey($key, $foundBanner);
        }
        self::assertNull($foundBanners[1]);
        self::assertCount(1, $container);
        /** @var Request $request */
        $request = $container[0]['request'];
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('https://example.com/api/v1/find', $request->getUri());
        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertContains('application/json', $request->getHeader('Content-Type'));
        $body = json_decode($request->getBody()->getContents(), true);
        self::assertCount(2, $body);
        self::assertArrayHasKey('zone_id', $body[0]);
        self::assertEquals($zone->uuid, $body[0]['zone_id']);
        self::assertArrayHasKey('zone_id', $body[1]);
        self::assertEquals($popupZone->uuid, $body[1]['zone_id']);
    }

    public function testFindBannersWhileSiteAcceptsDirectDealsOnly(): void
    {
        /** @var Zone $zone */
        [$publisher, $zone] = $this->initZone();
        $site = $zone->site;
        $site->only_direct_deals = true;
        $site->save();
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '1',
                'placementId' => $zone->uuid,
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler(
            [
                new Response(
                    body: json_encode(
                        [
                            '1' => [
                                [
                                    'banner_id' => $banner->uuid,
                                    'rpm' => 0.3,
                                ]
                            ]
                        ]
                    )
                ),
            ]
        );
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners[0];
        self::assertNotNull($foundBanner);
        /** @var Request $request */
        $request = $container[0]['request'];
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('https://example.com/api/v1/find', $request->getUri());
        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertContains('application/json', $request->getHeader('Content-Type'));
        $body = json_decode($request->getBody()->getContents(), true);
        self::assertTrue($body[0]['zone_options']['direct_deal']);
        self::assertEquals('example.com', $body[0]['banner_filters']['require']['require:site:domain']);
    }

    public function testFindBannersWhileZoneNotExist(): void
    {
        [$publisher, $zone] = $this->initZone();
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '1',
                'placementId' => '00000000000000000000000000000000',
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $mock = new MockHandler(
            [
                new Response(
                    body: json_encode(
                        [
                            '1' => [
                                [
                                    'banner_id' => $banner->uuid,
                                    'rpm' => 0.3,
                                ]
                            ]
                        ]
                    )
                ),
            ]
        );
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners[0];
        self::assertNull($foundBanner);
    }

    public function testFindBannersWhileSiteNotActive(): void
    {
        [$publisher, $zone] = $this->initZone();
        /** @var Site $site */
        $site = $zone->site;
        $site->status = Site::STATUS_INACTIVE;
        $site->saveOrFail();
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '1',
                'placementId' => $zone->uuid,
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $mock = new MockHandler(
            [
                new Response(
                    body: json_encode(
                        [
                            '1' => [
                                [
                                    'banner_id' => $banner->uuid,
                                    'rpm' => 0.3,
                                ]
                            ]
                        ]
                    )
                ),
            ]
        );
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners[0];
        self::assertNull($foundBanner);
    }

    public function testFindBannersFailWhileAdSelectUnavailable(): void
    {
        [$publisher, $zone] = $this->initZone();
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '1',
                'placementId' => $zone->uuid,
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $mock = new MockHandler([new Response(BaseResponse::HTTP_SERVICE_UNAVAILABLE, [], '{"error": "test"}')]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
    }

    public function testFindBannersFailWhileAdSelectResponseMalformed(): void
    {
        [$publisher, $zone] = $this->initZone();
        [$campaign, $banner] = $this->initBanner();
        $zones = [
            [
                'id' => '1',
                'placementId' => $zone->uuid,
            ],
        ];
        $context = new ImpressionContext(
            [
                'page' => 'https://example.com',
            ],
            [],
            [
                'keywords' => [],
                'tid' => 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ',
                'uid' => '22222222222222222222222222222222',
            ],
        );
        $impressionId = '0123456789ABCDEF0123456789ABCDEF';
        $mock = new MockHandler([new Response(body: '{ "1": [')]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(RuntimeException::class);

        $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);
    }

    /**
     * @dataProvider exportProvider
     */
    public function testExport(string $exportFunction, string $expectedUri, string $expectedKey): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(201),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $guzzleAdSelectClient->{$exportFunction}(new Collection());

        self::assertCount(1, $container);
        /** @var Request $request */
        $request = $container[0]['request'];
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('https://example.com' . $expectedUri, $request->getUri());
        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertContains('application/json', $request->getHeader('Content-Type'));
        $body = json_decode($request->getBody()->getContents(), true);
        self::assertArrayHasKey($expectedKey, $body);
    }

    public function exportProvider(): array
    {
        return [
            'exportBoostPayments' => ['exportBoostPayments', self::URI_BOOST_PAYMENT_EXPORT, 'payments'],
            'exportCases' => ['exportCases', self::URI_CASE_EXPORT, 'cases'],
            'exportCaseClicks' => ['exportCaseClicks', self::URI_CASE_CLICK_EXPORT, 'clicks'],
            'exportCasePayments' => ['exportCasePayments', self::URI_CASE_PAYMENT_EXPORT, 'payments'],
        ];
    }

    public function testExportFail(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(422),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->exportBoostPayments(new Collection());
    }

    /**
     * @dataProvider getExportedIdProvider
     */
    public function testGetExportedId(string $function, string $expectedUri): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, body: json_encode(['id' => 123])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $id = $guzzleAdSelectClient->{$function}();

        self::assertEquals(123, $id);
        /** @var Request $request */
        $request = $container[0]['request'];
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('https://example.com' . $expectedUri, $request->getUri());
    }

    public function getExportedIdProvider(): array
    {
        return [
            'getLastExportedBoostPaymentId' => [
                'getLastExportedBoostPaymentId',
                self::URI_BOOST_PAYMENT_EXPORT . '/last',
            ],
            'getLastExportedCaseId' => ['getLastExportedCaseId', self::URI_CASE_EXPORT . '/last'],
            'getLastExportedCaseClickId' => ['getLastExportedCaseClickId', self::URI_CASE_CLICK_EXPORT . '/last'],
            'getLastExportedCasePaymentId' => ['getLastExportedCasePaymentId', self::URI_CASE_PAYMENT_EXPORT . '/last'],
        ];
    }

    public function testGetExportedIdNotFound(): void
    {
        $mock = new MockHandler([
            new Response(404, body: 'Not found'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $id = $guzzleAdSelectClient->getLastExportedCaseId();

        self::assertEquals(0, $id);
    }

    public function testGetExportedIdFailOnInvalidResponseStatus(): void
    {
        $mock = new MockHandler([
            new Response(422, body: 'Unprocessable entity'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->getLastExportedCaseId();
    }

    public function testGetExportedIdFailOnEmptyResponse(): void
    {
        $mock = new MockHandler([
            new Response(201),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(RuntimeException::class);

        $guzzleAdSelectClient->getLastExportedCaseId();
    }

    public function testGetExportedIdFailOnInvalidResponse(): void
    {
        $mock = new MockHandler([
            new Response(200, body: 123)
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->getLastExportedCaseId();
    }

    private static function campaign(): Campaign
    {
        return new Campaign(
            Uuid::v4(),
            Uuid::v4(),
            'https://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1_000_000_000_000, null, null),
            new SourceCampaign('localhost', '0000-00000000-0001', '0.1', new DateTime(), new DateTime()),
            Status::processing(),
            'web',
            null
        );
    }

    private function initZone(): array
    {
        $publisher = User::factory()->create();
        $site = Site::factory()->create(
            [
                'domain' => 'example.com',
                'url' => 'https://example.com',
                'user_id' => $publisher,
            ]
        );
        $zone = Zone::factory()->create(['site_id' => $site]);
        return [$publisher, $zone];
    }

    private function initBanner(): array
    {
        $campaign = NetworkCampaign::factory()->create();
        $banner = NetworkBanner::factory()->create(['network_campaign_id' => $campaign]);
        return [$campaign, $banner];
    }
}
