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
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class GuzzleAdSelectClientTest extends TestCase
{
    private const URI_FIND_BANNERS = '/api/v1/find';
    private const URI_INVENTORY = '/api/v1/campaigns';

    public function testDeleteFromInventory(): void
    {
        $ids = ['10000000000000000000000000000000', '20000000000000000000000000000000'];
        $campaigns = new CampaignCollection();
        foreach ($ids as $id) {
            $campaigns->add(CampaignFactory::createFromArray($this->getCampaignData(['id' => new Uuid($id)])));
        }
        $client = self::createMock(Client::class);
        $client->expects(self::once())->method('delete')->with(
            self::URI_INVENTORY,
            self::callback(function ($options) use ($ids): bool {
                self::assertIsArray($options);
                self::assertArrayHasKey('json', $options);
                self::assertIsArray($options['json']);
                self::assertArrayHasKey('campaigns', $options['json']);
                self::assertIsArray($options['json']['campaigns']);
                self::assertEquals($ids, $options['json']['campaigns']);
                return true;
            })
        );
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        $guzzleAdSelectClient->deleteFromInventory($campaigns);
    }

    public function testDeleteFromInventoryFail(): void
    {
        $ids = ['10000000000000000000000000000000', '20000000000000000000000000000000'];
        $campaigns = new CampaignCollection();
        foreach ($ids as $id) {
            $campaigns->add(CampaignFactory::createFromArray($this->getCampaignData(['id' => new Uuid($id)])));
        }
        $mock = new MockHandler([
            new Response(500),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $guzzleAdSelectClient->deleteFromInventory($campaigns);
    }

    public function testFindBanners(): void
    {
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create([
            'network_campaign_id' => NetworkCampaign::factory()->create(),
            'serve_url' => 'https://adshares.net/serve/' . Uuid::v4()->toString(),
        ]);
        $responseBody = [
            '1' => [
                [
                    'banner_id' => $networkBanner->uuid,
                    'rpm' => 0.5,
                ],
            ],
        ];
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('post')
            ->with(
                self::URI_FIND_BANNERS,
                self::callback(function ($options): bool {
                    self::assertIsArray($options);
                    self::assertArrayHasKey('json', $options);
                    self::assertIsArray($options['json']);
                    self::assertCount(1, $options['json']);
                    self::assertArrayHasKey('banner_filters', $options['json'][0]);
                    $filters = $options['json'][0]['banner_filters'];
                    self::assertEquals(['image'], $filters['require']['type']);
                    self::assertEquals(['image/png'], $filters['require']['mime']);
                    self::assertEquals(['source_host' => ['http://localhost:8000']], $filters['exclude']);
                    return true;
                })
            )
            ->willReturn(new Response(body: json_encode($responseBody)));
        $guzzleAdSelectClient = new GuzzleAdSelectClient($client);

        /** @var Zone $zone */
        $zone = Zone::factory()
            ->create(['site_id' => Site::factory()->create(['user_id' => User::factory()->create()])]);
        $zones = [
            [
                'id' => '1',
                'placementId' => $zone->uuid,
                'options' => [
                    'banner_mime' => ['image/png'],
                    'banner_type' => ['image'],
                    'exclude' => ['source_host' => ['http://localhost:8000']],
                ]
            ],
        ];
        $context = new ImpressionContext(
            [],
            [],
            [
                'keywords' => [],
                'uid' => Uuid::v4()->toString(),
            ]
        );
        $impressionId = Uuid::v4()->toString();

        $foundBanners = $guzzleAdSelectClient->findBanners($zones, $context, $impressionId);

        self::assertCount(1, $foundBanners);
        self::assertNotNull($foundBanners[0]);
        self::assertEquals($networkBanner->uuid, $foundBanners[0]['id']);
    }

    private function getCampaignData(array $merge = []): array
    {
        return array_merge(
            [
                'id' => Uuid::v4(),
                'demand_id' => Uuid::v4(),
                'landing_url' => 'https://exmaple.com',
                'date_start' => new DateTime('-1 day'),
                'date_end' => new DateTime('+2 days'),
                'created_at' => new DateTime('-1 days'),
                'updated_at' => new DateTime('-1 days'),
                'source_campaign' => [
                    'host' => 'localhost:8101',
                    'address' => '0001-00000001-8B4E',
                    'version' => '0.1',
                    'created_at' => new DateTime(),
                    'updated_at' => new DateTime(),
                ],
                'banners' => [],
                'max_cpc' => null,
                'max_cpm' => null,
                'budget' => 1_000_000_000_000,
                'demand_host' => 'localhost:8101',
                'medium' => 'web',
                'vendor' => null,
                'targeting_excludes' => [],
                'targeting_requires' => [],
            ],
            $merge,
        );
    }
}
