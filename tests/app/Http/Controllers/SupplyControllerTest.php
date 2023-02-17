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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Client\GuzzleAdSelectClient;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Mock\Client\DummyAdUserClient;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class SupplyControllerTest extends TestCase
{
    private const BANNER_FIND_URI = '/supply/find';
    private const PAGE_WHY_URI = '/supply/why';
    private const REPORT_AD_URI = '/supply/ad/report';
    private const SUPPLY_ANON_URI = '/supply/anon';
    private const TARGETING_REACH_URI = '/supply/targeting-reach';
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
        'data' => [
            '*' => [
                'id',
                'creativeId',
                'placementId',
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
            ],
        ]
    ];
    private const FOUND_BANNERS_WITH_CREATION_STRUCTURE = [
        'banners' => [
            '*' => self::LEGACY_FOUND_BANNERS_STRUCTURE,
        ],
        'success',
    ];
    private const TARGETING_REACH_STRUCTURE = [
        'meta' => [
            'total_events_count',
            'updated_at',
        ],
        'categories' => [
            '*' => [],
        ],
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

    public function testReportAd(): void
    {
        Storage::fake('local');
        /** @var User $user */
        $user = User::factory()->create();
        /** @var NetworkCampaign $campaign */
        $campaign = NetworkCampaign::factory()->create();
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create(['network_campaign_id' => $campaign]);
        /** @var NetworkCase $case */
        $case = NetworkCase::factory()->create([
            'banner_id' => $banner->uuid,
            'campaign_id' => $campaign->uuid,
            'publisher_id' => $user->uuid,
        ]);

        $response = $this->get(self::buildUriReportAd($case->case_id, $banner->uuid));

        $response->assertStatus(Response::HTTP_OK);
        Storage::disk('local')->assertExists('reported-ads.txt');
    }

    public function testReportAdWhileInvalidCaseFormat(): void
    {
        $response = $this->get(self::buildUriReportAd('00', 'asdf'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testReportAdWhileNotExistingCase(): void
    {
        $response = $this->get(self::buildUriReportAd(
            '00000000000000000000000000000000',
            '00000000000000000000000000000000',
        ));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testReportAdWhileBannerIdDoesNotMatchCase(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var NetworkCampaign $campaign */
        $campaign = NetworkCampaign::factory()->create();
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create(['network_campaign_id' => $campaign]);
        /** @var NetworkCase $case */
        $case = NetworkCase::factory()->create([
            'banner_id' => $banner->uuid,
            'campaign_id' => $campaign->uuid,
            'publisher_id' => $user->uuid,
        ]);

        $response = $this->get(self::buildUriReportAd($case->case_id, '00000000000000000000000000000000'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function buildUriReportAd(string $caseId, string $bannerId): string
    {
        return sprintf('%s/%s/%s', self::REPORT_AD_URI, $caseId, $bannerId);
    }

    public function testFind(): void
    {
        $this->mockAdSelect();
        $adUser = self::createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('getUserContext')
            ->willReturnCallback(function ($context) {
                self::assertInstanceOf(ImpressionContext::class, $context);
                $contextArray = $context->toArray();
                self::assertEquals(1, $contextArray['device']['extensions']['metamask']);
                self::assertEquals('good-user', $contextArray['user']['account']);
                return (new DummyAdUserClient())->getUserContext($context);
            });
        $this->instance(AdUser::class, $adUser);
        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'status' => Site::STATUS_ACTIVE]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site->id]);
        $data = [
            'context' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
                'metamask' => true,
                'uid' => 'good-user',
            ],
            'placements' => [
                [
                    'id' => '3',
                    'placementId' => $zone->uuid,
                ],
            ],
        ];

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FIND_BANNER_STRUCTURE);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', '3');
    }

    public function testFindWhileNoBanners(): void
    {
        $this->app->bind(
            AdSelect::class,
            function () {
                $adSelect = self::createMock(AdSelect::class);
                $adSelect->method('findBanners')->willReturnCallback(function (array $zones) {
                    return new FoundBanners(array_map(fn($zone) => null, $zones));
                });
                return $adSelect;
            }
        );

        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'status' => Site::STATUS_ACTIVE]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site->id]);
        $data = [
            'context' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
            ],
            'placements' => [
                [
                    'id' => '1',
                    'placementId' => $zone->uuid,
                ],
            ],
        ];

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FIND_BANNER_STRUCTURE);
        $response->assertJsonCount(0, 'data');
    }

    public function testFindWithoutPlacements(): void
    {
        $data = [
            'context' => [
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

    public function testFindDynamicWithoutExistingUser(): void
    {
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '1']);
        $this->mockAdSelect();
        $data = self::getDynamicFindData();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FIND_BANNER_STRUCTURE);
        $response->assertJsonCount(1, 'data');
        self::assertNotNull(User::firstOrFail()->admin_confirmed_at);
    }

    public function testFindDynamicWhileSiteApprovalRequired(): void
    {
        Config::updateAdminSettings([Config::SITE_APPROVAL_REQUIRED => '*']);
        $this->mockAdSelect();
        $data = self::getDynamicFindData();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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
            'unsupported popup' => [
                self::getDynamicFindData(['placements' => [
                    self::getPlacementData(['types' => [Banner::TEXT_TYPE_DIRECT_LINK]])
                ]]),
            ],
            'missing context.medium' => [
                self::getDynamicFindData(['context' => self::getContextData(remove: 'medium')])
            ],
            'missing context.url' => [self::getDynamicFindData(['context' => self::getContextData(remove: 'url')])],
            'invalid context type' => [self::getDynamicFindData(['context' => 1])],
            'invalid context.url type' => [self::getDynamicFindData(['context' => self::getContextData(['url' => 1])])],
            'invalid context.medium type' => [
                self::getDynamicFindData(['context' => self::getContextData(['medium' => 1])])
            ],
            'invalid context.medium value' => [
                self::getDynamicFindData(['context' => self::getContextData(['medium' => 'invalid'])])
            ],
            'invalid context.metamask type' => [
                self::getDynamicFindData(['context' => self::getContextData(['metamask' => 'metamask'])])
            ],
            'invalid context.publisher type' => [
                self::getDynamicFindData(['context' => self::getContextData(['publisher' => 1])])
            ],
            'invalid context.uid type' => [
                self::getDynamicFindData(['context' => self::getContextData(['uid' => 12])])
            ],
            'invalid context.vendor type' => [
                self::getDynamicFindData(['context' => self::getContextData(['vendor' => 12])])
            ],
            'invalid placements type' => [self::getDynamicFindData(['placements' => 1])],
            'invalid placements[] type' => [self::getDynamicFindData(['placements' => [1]])],
            'conflicting placement types' => [
                self::getDynamicFindData([
                    'placements' => [
                        self::getPlacementData(['types' => [Banner::TEXT_TYPE_IMAGE, Banner::TEXT_TYPE_DIRECT_LINK]])
                    ],
                ])
            ],
            'empty placement types' => [
                self::getDynamicFindData([
                    'placements' => [
                        self::getPlacementData(['types' => []])
                    ],
                ])
            ],
            'empty placement mimes' => [
                self::getDynamicFindData([
                    'placements' => [
                        self::getPlacementData(['mimes' => []])
                    ],
                ])
            ],
            'no matching scopes' => [
                self::getDynamicFindData([
                    'context' => self::getContextData(['medium' => 'metaverse', 'vendor' => 'decentraland']),
                    'placements' => [
                        self::getPlacementData(['width' => '30000'])
                    ],
                ])
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

    /**
     * @dataProvider findJsonFailProvider
     */
    public function testFindJsonFail(array $data): void
    {
        Config::updateAdminSettings([Config::AUTO_REGISTRATION_ENABLED => '1']);
        $this->mockAdSelect();

        $response = self::post(self::SUPPLY_ANON_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function findJsonFailProvider(): array
    {
        return [
            'invalid medium' => [self::findJsonData(['medium' => 'invalid'])],
            'no matching scope' => [self::findJsonData(['width' => '30000'])],
        ];
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

    public function testTargetingReachList(): void
    {
        Config::updateAdminSettings([Config::INVENTORY_EXPORT_WHITELIST => config('app.adshares_address')]);
        /** @var NetworkHost $networkHost */
        $networkHost = NetworkHost::factory()->create(['address' => '0001-00000005-CBCA']);
        NetworkVectorsMeta::factory()->create(['network_host_id' => $networkHost->id]);
        DB::insert(
            "INSERT INTO network_vectors (
                network_host_id,
                `key`,
                occurrences,
                cpm_25,
                cpm_50,
                cpm_75,
                negation_cpm_25,
                negation_cpm_50,
                negation_cpm_75,
                data
            )
            VALUES (
                :hostId,
                'site:domain:adshares.net',
                1,
                2814662,
                2814662,
                2814662,
                1107544,
                3339398,
                3339398,
                0
            );",
            [':hostId' => $networkHost->id],
        );
        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);

        $response = self::getJson(
            self::TARGETING_REACH_URI,
            [
                'Authorization' => $authenticator->getHeader(
                    config('app.adshares_address'),
                    Crypt::decryptString(config('app.adshares_secret')),
                ),
            ],
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::TARGETING_REACH_STRUCTURE);
    }

    public function testTargetingReachWhileNotAuthorized(): void
    {
        Config::updateAdminSettings([Config::INVENTORY_EXPORT_WHITELIST => '0001-00000002-BB2D']);

        $response = self::getJson(self::TARGETING_REACH_URI);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testTargetingReachWhileInvalidCredentials(): void
    {
        Config::updateAdminSettings([Config::INVENTORY_EXPORT_WHITELIST => '0001-00000002-BB2D']);

        $response = self::getJson(self::TARGETING_REACH_URI, ['Authorization' => '']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testTargetingReachListWhileHostIsMissing(): void
    {
        $response = self::getJson(self::TARGETING_REACH_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::TARGETING_REACH_STRUCTURE);
    }

    public function testTargetingReachListWhileMetaDataIsMissing(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000005-CBCA']);

        $response = self::getJson(self::TARGETING_REACH_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::TARGETING_REACH_STRUCTURE);
    }

    public function testLogNetworkView(): void
    {
        [$query, $banner, $zone] = self::initNetworkForLoggingView();

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringStartsWith('https://example.com/view', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $locationQuery);
        foreach (['cid', 'ctx', 'iid', 'pto', 'pid'] as $key) {
            self::assertArrayHasKey($key, $locationQuery);
        }
        self::assertEquals('13245679801324567980132456798012', $locationQuery['cid']);
        self::assertEquals('0001-00000005-CBCA', $locationQuery['pto']);
    }

    public function testLogNetworkViewWhileCaseIdAndImpressionIdAreUuidV4(): void
    {
        [$query, $banner, $zone] = self::initNetworkForLoggingView();
        $query['cid'] = Uuid::fromString($query['cid'])->toString();
        $query['iid'] = Uuid::fromString(NetworkImpression::first()->impression_id)->toString();

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringStartsWith('https://example.com/view', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $locationQuery);
        foreach (['cid', 'ctx', 'iid', 'pto', 'pid'] as $key) {
            self::assertArrayHasKey($key, $locationQuery);
        }
        self::assertEquals('13245679-8013-2456-7980-132456798012', $locationQuery['cid']);
        self::assertEquals('0001-00000005-CBCA', $locationQuery['pto']);
    }

    public function testLogNetworkViewFailWhileImpressionIdIsMissing(): void
    {
        [$query, $banner, $zone] = self::initNetworkForLoggingView();
        unset($query['iid']);

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testLogNetworkViewFailWhileImpressionIdIsInvalid(): void
    {
        [$query, $banner, $zone] = self::initNetworkForLoggingView();
        $query['iid'] = '0123456789ABCDEF0123456789ABCDEF';

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private static function findJsonData(array $merge = []): array
    {
        return array_merge(
            [
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
            ],
            $merge,
        );
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
            'context' => self::getContextData(),
            'placements' => [
                self::getPlacementData(),
            ],
        ], $merge);
    }

    private static function getContextData(array $merge = [], string $remove = null): array
    {
        $data = array_merge([
            'iid' => '0123456789ABCDEF0123456789ABCDEF',
            'url' => 'https://example.com',
            'publisher' => 'ADS:0001-00000001-8B4E',
            'medium' => 'web',
        ], $merge);
        if (null !== $remove) {
            unset($data[$remove]);
        }
        return $data;
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

    private static function buildLogViewUri(string $bannerId, ?array $query = null): string
    {
        $uri = sprintf('/l/n/view/%s', $bannerId);
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }

    private static function initNetworkForLoggingView(): array
    {
        /** @var NetworkImpression $impression */
        $impression = NetworkImpression::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => User::factory()->create()]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);
        $campaign = NetworkCampaign::factory()->create();
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create([
            'network_campaign_id' => $campaign,
            'view_url' => 'https://example.com/view',
        ]);
        $iid = Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($impression->impression_id));
        $ctx = Utils::encodeZones(
            [
                'page' => [
                    'iid' => $iid,
                    'frame' => 0,
                    'width' => 1024,
                    'height' => 768,
                    'url' => 'https://adshares.net',
                    'keywords' => '',
                    'metamask' => 0,
                    'ref' => '',
                    'pop' => 0,
                    'zone' => $zone->uuid,
                    'options' => '[]',
                ],
            ]
        );
        $redirectUrl = Utils::urlSafeBase64Encode($banner->view_url);
        $query = [
            'cid' => '13245679801324567980132456798012',
            'ctx' => $ctx,
            'iid' => $iid,
            'r' => $redirectUrl,
        ];
        return [$query, $banner, $zone];
    }
}
