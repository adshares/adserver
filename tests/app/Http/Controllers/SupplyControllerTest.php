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
use Adshares\Adserver\Models\NetworkCaseClick;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Mock\Client\DummyAdUserClient;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;

// phpcs:ignoreFile PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found
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
        $address = '0001-00000003-AB0C';
        NetworkHost::factory()->create(['address' => $address]);
        $campaign = NetworkCampaign::factory()->create(['source_address' => $address]);
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => $campaign]);
        $query = [
            'bid' => $banner->uuid,
            'cid' => '0123456789abcdef0123456789abcdef',
        ];

        $response = $this->get(self::PAGE_WHY_URI . '?' . http_build_query($query));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewHas('demand', true);
    }

    public function testPageWhyWhileCaseIdAndBannerIdAreUuid(): void
    {
        $address = '0001-00000003-AB0C';
        NetworkHost::factory()->create(['address' => $address]);
        $campaign = NetworkCampaign::factory()->create(['source_address' => $address]);
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => $campaign]);
        $query = [
            'bid' => Uuid::fromString($banner->uuid)->toString(),
            'cid' => Uuid::uuid4()->toString(),
        ];

        $response = $this->get(self::PAGE_WHY_URI . '?' . http_build_query($query));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewHas('demand', true);
    }

    public function testPageWhileBannerFromDsp(): void
    {
        $address = '0001-00000001-8B4E';
        $host = 'https://example.com';
        Config::updateAdminSettings([
            Config::DSP_BRIDGE_ACCOUNT_ADDRESS => $address,
            Config::DSP_BRIDGE_URL => $host,
        ]);
        NetworkHost::factory()->create(['address' => $address]);
        $campaign = NetworkCampaign::factory()->create([
            'source_address' => $address,
            'source_host' => $host,
        ]);
        /** @var NetworkBanner $banner */
        $banner = NetworkBanner::factory()->create(['id' => 1, 'network_campaign_id' => $campaign]);
        $query = [
            'bid' => $banner->uuid,
            'cid' => '0123456789abcdef0123456789abcdef',
        ];

        $response = $this->get(self::PAGE_WHY_URI . '?' . http_build_query($query));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewHas('demand', true);
        $response->assertViewHas('demandName', 'AdServer');
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
        $this->initAdUser();
        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => Site::factory()->create(['user_id' => $user])]);
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

    public function testFindFailWhileInvalidPlacement(): void
    {
        $data = [
            'context' => [
                'iid' => '0123456789ABCDEF0123456789ABCDEF',
                'url' => 'https://example.com',
                'metamask' => true,
                'uid' => 'good-user',
            ],
            'placements' => [
                'id' => '3',
            ],
        ];

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFindDspBridge(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'example.com/bid' => Http::response([[
                'request_id' => '0',
                'ext_id' => '1',
                'serve_url' => 'https://example.com/serve/1',
            ]]),
        ]);
        $data = $this->initDspBridge();

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FIND_BANNER_STRUCTURE);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', '3');
        Http::assertSentCount(1);
    }

    public function testFindDspBridgeWhileEmptyResponse(): void
    {
        Http::preventStrayRequests();
        Http::fake(['example.com/bid' => Http::response([])]);
        $data = $this->initDspBridge();
        $dspNetworkBanner = (new NetworkBanner())->first();
        /** @var NetworkBanner $adserverNetworkBanner */
        $adserverNetworkBanner = NetworkBanner::factory()->create([
            'network_campaign_id' => NetworkCampaign::factory()->create(),
            'serve_url' => 'https://adshares.net/serve/' . Uuid::uuid4()->toString(),
        ]);
        $zone = Zone::fetchByPublicId($data['placements'][0]['placementId']);
        $impressionId = $data['context']['iid'];
        $adSelect = self::createMock(AdSelect::class);
        $adSelect->expects(self::exactly(2))->method('findBanners')->willReturnOnConsecutiveCalls(
            new FoundBanners([$this->createFoundBanner($dspNetworkBanner, $zone, $impressionId)]),
            new FoundBanners([$this->createFoundBanner($adserverNetworkBanner, $zone, $impressionId)]),
        );
        $this->app->bind(AdSelect::class, fn () => $adSelect);

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FIND_BANNER_STRUCTURE);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', '3');
        $response->assertJsonPath('data.0.serveUrl', $adserverNetworkBanner->serve_url);
        Http::assertSentCount(1);
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
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => Site::factory()->create(['user_id' => $user])]);
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

    public function testFindFailWhileSiteRejected(): void
    {
        SitesRejectedDomain::factory()->create(['domain' => 'example.com']);

        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => Site::factory()->create(['user_id' => $user])]);
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

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testFindFailWhilePlacementInFrame(): void
    {
        Config::updateAdminSettings([Config::ALLOW_ZONE_IN_IFRAME => '0']);
        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => Site::factory()->create(['user_id' => $user])]);
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
                    'topframe' => false,
                ],
            ],
        ];

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    /**
     * @dataProvider findFailProvider
     */
    public function testFindFail(array $data): void
    {
        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function findFailProvider(): array
    {
        return [
            'without placements' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                ]
            ],
            'invalid placements[] type' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        'id' => '1',
                        'placementId' => '0123456789ABCDEF0123456789ABCDEF',
                    ],
                ]
            ],
            'missing placement id' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        [
                            'placementId' => '0123456789ABCDEF0123456789ABCDEF',
                        ]
                    ],
                ]
            ],
            'missing placement placementId' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        [
                            'id' => '1',
                        ]
                    ],
                ]
            ],
            'invalid placement id type' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        [
                            'id' => 1,
                            'placementId' => '0123456789ABCDEF0123456789ABCDEF',
                        ]
                    ],
                ]
            ],
            'invalid placement placementId format' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        [
                            'id' => '1',
                            'placementId' => '0123456789ABCDEF',
                        ]
                    ],
                ]
            ],
            'invalid placement mimes type' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        [
                            'id' => '1',
                            'placementId' => '0123456789ABCDEF0123456789ABCDEF',
                            'mimes' => 'image/png',
                        ]
                    ],
                ]
            ],
            'invalid placement mimes[] type' => [
                [
                    'context' => [
                        'iid' => '0123456789ABCDEF0123456789ABCDEF',
                        'url' => 'https://example.com',
                    ],
                    'placements' => [
                        [
                            'id' => '1',
                            'placementId' => '0123456789ABCDEF0123456789ABCDEF',
                            'mimes' => [true],
                        ]
                    ],
                ]
            ],
        ];
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

    public function testFindDynamicWithPublisherIdAsUuidV4(): void
    {
        /** @var User $publisher */
        $publisher = User::factory()->create();
        $publisherId = Uuid::fromString($publisher->uuid)->toString();
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '1']);
        $this->mockAdSelect();
        $data = self::getDynamicFindData(['context' => self::getContextData(['publisher' => $publisherId])]);

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::FIND_BANNER_STRUCTURE);
        $response->assertJsonCount(1, 'data');
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

    public function testFindDynamicWithoutExistingUserWhileInvalidPublisherId(): void
    {
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '1']);
        $this->mockAdSelect();
        $data = self::getDynamicFindData(['context' => self::getContextData(['publisher' => '0001-00000001-8B4E'])]);

        $response = $this->postJson(self::BANNER_FIND_URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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
                self::getDynamicFindData([
                    'placements' => [
                        self::getPlacementData(['types' => [Banner::TEXT_TYPE_DIRECT_LINK]])
                    ]
                ]),
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
            'invalid placement topframe' => [
                self::getDynamicFindData([
                    'placements' => [
                        self::getPlacementData(['topframe' => null])
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

    public function testLogNetworkClick(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingClick();

        $response = $this->get(self::buildLogClickUri($banner->uuid, $query));

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
        self::assertDatabaseCount(NetworkCaseClick::class, 1);
    }

    public function testLogNetworkClickWithoutContext(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingClick();
        unset($query['ctx']);
        $query['zid'] = $zone->uuid;

        $response = $this->get(self::buildLogClickUri($banner->uuid, $query));

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
        self::assertDatabaseCount(NetworkCaseClick::class, 1);
    }

    public function testLogNetworkClickFailWhileNoView(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();

        $response = $this->get(self::buildLogClickUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testLogNetworkClickFailWhileInvalidRedirectUrlAndBannerId(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingClick();
        $query['r'] = '';

        $response = $this->get(self::buildLogClickUri('invalid', $query));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testLogNetworkClickWhileBannerFromBridgeRedirection(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingClick();
        unset($query['r']);
        $query['extid'] = '12';
        Http::preventStrayRequests();
        Http::fake(['example.com/click?*' => Http::response(['redirect_url' => 'https://adshares.net/click'])]);

        $response = $this->get(self::buildLogClickUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringStartsWith('https://adshares.net/click', $location);
        self::assertDatabaseCount(NetworkCaseClick::class, 1);
        Http::assertSentCount(1);
    }

    public function testLogNetworkClickWhileBannerFromBridgeNoRedirection(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingClick();
        unset($query['r']);
        $query['extid'] = '12';
        Http::preventStrayRequests();
        Http::fake(['example.com/click?*' => Http::response(status: Response::HTTP_NO_CONTENT)]);

        $response = $this->get(self::buildLogClickUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringContainsString('/supply/why?', $location);
        self::assertDatabaseCount(NetworkCaseClick::class, 1);
        Http::assertSentCount(1);
    }

    public function testLogNetworkClickWhileBannerFromBridgeNoUrl(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingClick();
        unset($query['r']);
        $query['extid'] = '12';
        $banner->click_url = '';
        $banner->saveOrFail();
        Http::preventStrayRequests();

        $response = $this->get(self::buildLogClickUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        Http::assertNothingSent();
    }

    public function testLogNetworkView(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query), ['Origin' => 'https://example.com']);

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://example.com');
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

    public function testLogNetworkViewWithoutContext(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        unset($query['ctx']);
        $query['zid'] = $zone->uuid;

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
        $context = Utils::decodeZones($locationQuery['ctx']);
        self::assertEquals('0x05cf6d580d124d6eda7fd065b2cd239b08e2fd68', $context['user']['account']);
    }

    public function testLogNetworkViewWhileCaseIdAndImpressionIdAreUuidV4(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
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

    public function testLogNetworkViewWithoutContextFailWhileInvalidZoneId(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        unset($query['ctx']);
        $query['zid'] = 'invalid';

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testLogNetworkViewWhileCaseIdAndImpressionIdAndZoneIdAreUuidV4(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        $query['cid'] = Uuid::fromString($query['cid'])->toString();
        $query['iid'] = Uuid::fromString(NetworkImpression::first()->impression_id)->toString();
        $query['zid'] = Uuid::fromString($zone->uuid)->toString();

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

    public function testLogNetworkViewFailWhileInvalidRedirectUrlAndBannerId(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        $query['r'] = '';

        $response = $this->get(self::buildLogViewUri('invalid', $query));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testLogNetworkViewFailWhileImpressionIdIsMissing(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        unset($query['iid']);

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testLogNetworkViewFailWhileImpressionIdIsInvalid(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        $query['iid'] = '0123456789ABCDEF0123456789ABCDEF';

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testLogNetworkViewWhileBannerFromBridgeRedirection(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        unset($query['r']);
        $query['extid'] = '12';
        Http::preventStrayRequests();
        Http::fake(['example.com/view?*' => Http::response(['redirect_url' => 'https://adshares.net/view'])]);

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringStartsWith('https://adshares.net/view', $location);
        Http::assertSentCount(1);
    }

    public function testLogNetworkViewWhileBannerFromBridgeNoRedirection(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        unset($query['r']);
        $query['extid'] = '12';
        Http::preventStrayRequests();
        Http::fake(['example.com/view?*' => Http::response(status: Response::HTTP_NO_CONTENT)]);

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        Http::assertSentCount(1);
    }

    public function testLogNetworkViewWhileBannerFromBridgeNoUrl(): void
    {
        [$query, $banner, $zone] = self::initBeforeLoggingView();
        $banner->view_url = '';
        $banner->saveOrFail();
        unset($query['r']);
        $query['extid'] = '12';
        Http::preventStrayRequests();

        $response = $this->get(self::buildLogViewUri($banner->uuid, $query), ['Origin' => 'https://example.com']);

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://example.com');
        Http::assertNothingSent();
    }

    public function testRegister(): void
    {
        Config::updateAdminSettings([Config::ADUSER_SERVE_SUBDOMAIN => 'au']);
        $expectedTrackingId = 'LWuhOmg74MmOJ7lLXA65oktx8iLvmQ';

        $response = $this->get('/supply/register?iid=1a06e492-35df-4545-9e12-d5d929abf9e9');

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        self::assertStringContainsString('/' . $expectedTrackingId . '/', $response->headers->get('Location'));
        $response->assertCookie('tid', $expectedTrackingId, false);
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
                    'user' => [
                        'account' => '0x05cf6d580d124d6eda7fd065b2cd239b08e2fd68',
                        'language' => 'en',
                    ],
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
        $this->app->bind(AdSelect::class, fn () => new GuzzleAdSelectClient($client));
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

    private static function buildLogClickUri(string $bannerId, ?array $query = null): string
    {
        $uri = sprintf('/l/n/click/%s', $bannerId);
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }

    private static function buildLogViewUri(string $bannerId, ?array $query = null): string
    {
        $uri = sprintf('/l/n/view/%s', $bannerId);
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }

    private static function initBeforeLoggingClick(): array
    {
        $arr = self::initBeforeLoggingView();
        $query = $arr[0];
        $banner = $arr[1];
        $zone = $arr[2];
        NetworkCase::factory()->create([
            'banner_id' => $banner->uuid,
            'case_id' => $query['cid'],
            'network_impression_id' => NetworkImpression::firstOrFail()->id,
            'publisher_id' => $zone->site->user->uuid,
            'site_id' => $zone->site->uuid,
            'zone_id' => $zone->uuid,
        ]);
        return $arr;
    }

    private static function initBeforeLoggingView(): array
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
            'click_url' => 'https://example.com/click',
            'network_campaign_id' => $campaign,
            'view_url' => 'https://example.com/view',
        ]);
        $iid = Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($impression->impression_id));
        $ctx = Utils::UrlSafeBase64Encode(
            json_encode(
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
            )
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

    private function initAdUser(): void
    {
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
    }

    private function initDspBridge(): array
    {
        Config::updateAdminSettings([
            Config::DSP_BRIDGE_ACCOUNT_ADDRESS => '0001-00000001-8B4E',
            Config::DSP_BRIDGE_URL => 'https://example.com',
        ]);
        NetworkHost::factory()->create([
            'address' => '0001-00000001-8B4E',
            'host' => 'https://example.com',
        ]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create([
            'site_id' => Site::factory()->create([
                'user_id' => User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]),
                'status' => Site::STATUS_ACTIVE,
            ]),
        ]);
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create([
            'network_campaign_id' => NetworkCampaign::factory()->create(),
            'serve_url' => 'https://example.com/serve/' . Uuid::uuid4()->toString(),
        ]);
        $impressionId = Uuid::uuid4();
        $adSelect = self::createMock(AdSelect::class);
        $adSelect->method('findBanners')->willReturn(
            new FoundBanners([$this->createFoundBanner($networkBanner, $zone, $impressionId)])
        );
        $this->app->bind(AdSelect::class, fn () => $adSelect);
        $this->initAdUser();
        return [
            'context' => [
                'iid' => $impressionId,
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
    }

    private function createFoundBanner(
        NetworkBanner $networkBanner,
        Zone $zone,
        UuidInterface $impressionId
    ): array {
        return [
            'id' => $networkBanner->uuid,
            'demand_id' => $networkBanner->demand_banner_id,
            'publisher_id' => '0123456879ABCDEF0123456879ABCDEF',
            'zone_id' => $zone->uuid,
            'pay_from' => '0001-00000001-8B4E',
            'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
            'type' => $networkBanner->type,
            'size' => $networkBanner->size,
            'serve_url' => $networkBanner->serve_url,
            'creative_sha1' => '',
            'click_url' => SecureUrl::change(
                route(
                    'log-network-click',
                    [
                        'id' => $networkBanner->uuid,
                        'iid' => $impressionId,
                        'r' => Utils::urlSafeBase64Encode($networkBanner->click_url),
                        'zid' => $zone->uuid,
                    ]
                )
            ),
            'view_url' => SecureUrl::change(
                route(
                    'log-network-view',
                    [
                        'id' => $networkBanner->uuid,
                        'iid' => $impressionId,
                        'r' => Utils::urlSafeBase64Encode($networkBanner->view_url),
                        'zid' => $zone->uuid,
                    ]
                )
            ),
            'info_box' => true,
            'rpm' => 0.5,
            'request_id' => '3',
        ];
    }
}
