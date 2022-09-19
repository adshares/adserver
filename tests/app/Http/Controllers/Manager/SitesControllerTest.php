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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Mock\Client\DummyAdUserClient;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use DateTime;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function GuzzleHttp\json_decode;

class SitesControllerTest extends TestCase
{
    private const URI = '/api/sites';
    private const URI_CRYPTOVOXELS_CODE = '/api/sites/cryptovoxels/code';
    private const URI_DOMAIN_VERIFY = '/api/sites/domain/validate';

    private const SITE_STRUCTURE = [
        'id',
        'name',
        'domain',
        'url',
        'filtering',
        'onlyAcceptedBanners',
        'adUnits' => [
            '*' => [
                'code',
                'label',
                'name',
                'size',
                'status',
                'tags',
                'type',
            ],
        ],
        'status',
        'primaryLanguage',
    ];

    private const DOMAIN_VERIFY_STRUCTURE = [
        'code',
        'message',
    ];

    private const RANK_STRUCTURE = [
        'rank',
        'info',
    ];

    private const SIZES_STRUCTURE = [
        'sizes' => [],
    ];

    private static function getSiteUri(int $siteId): string
    {
        return self::URI . '/' . $siteId;
    }

    public function testEmptyDb(): void
    {
        $this->setupUser();

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::getSiteUri(1));
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider creationDataProvider
     */
    public function testCreateSite($data, $preset): void
    {
        $this->setupUser();

        $response = $this->postJson(self::URI, ['site' => $data]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::getSiteUri($id));
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::SITE_STRUCTURE)
            ->assertJsonFragment(
                [
                    'name' => $preset['name'],
                    'primaryLanguage' => $preset['primaryLanguage'],
                    'status' => $preset['status'],
                ]
            )
            ->assertJsonCount(1, 'adUnits')
            ->assertJsonCount(2, 'filtering')
            ->assertJsonCount(1, 'filtering.requires')
            ->assertJsonCount(0, 'filtering.excludes');
    }

    private function getIdFromLocation($location): string
    {
        $matches = [];
        $this->assertSame(1, preg_match('/(\d+)$/', $location, $matches));

        return $matches[1];
    }

    public function testCreateMultipleSites(): void
    {
        $user = $this->setupUser();

        array_map(
            function () use ($user) {
                Site::factory()->create(['user_id' => $user->id]);
            },
            $this->creationDataProvider()
        );

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);
        $response->assertJsonStructure(
            [
                '*' => self::SITE_STRUCTURE,
            ]
        );
    }

    public function creationDataProvider(): array
    {
        $presets = [
            [
                'status' => 0,
                'name' => 'nameA',
                'url' => 'https://example.com',
                'primaryLanguage' => 'pl',
                'medium' => 'web',
                'vendor' => null,
            ],
            [
                'status' => 1,
                'name' => 'nameB',
                "url" => 'https://example.com',
                'primaryLanguage' => 'en',
                'medium' => 'web',
                'vendor' => null,
            ],
        ];

        $default = json_decode(
            <<<JSON
{
    "filtering": {
      "requires": {
        "category": [
          "1"
        ]
      },
      "excludes": {}
    },
    "onlyAcceptedBanners": false,
    "adUnits": [
      {
        "name": "ssss",
        "size": "300x250"
      }
    ],
    "categories": [
      "unknown"
    ]
  }
JSON
            ,
            true
        );

        return array_map(
            function ($preset) use ($default) {
                return [array_merge($default, $preset), $preset];
            },
            $presets
        );
    }

    /**
     * @dataProvider createSiteUnprocessableProvider
     *
     * @param array $siteData
     * @param int $expectedStatus
     */
    public function testCreateSiteUnprocessable(array $siteData, int $expectedStatus): void
    {
        $this->setupUser();

        $response = $this->postJson(self::URI, ['site' => $siteData]);

        $response->assertStatus($expectedStatus);
    }

    public function createSiteUnprocessableProvider(): array
    {
        return [
            'no data' => [[], Response::HTTP_UNPROCESSABLE_ENTITY],
            'correct' => [self::simpleSiteData(), Response::HTTP_CREATED],
            'missing name' => [self::simpleSiteData([], 'name'), Response::HTTP_UNPROCESSABLE_ENTITY],
            'missing language' => [self::simpleSiteData([], 'primaryLanguage'), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid language' => [
                self::simpleSiteData(['primaryLanguage' => 'English']),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'missing status' => [self::simpleSiteData([], 'status'), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid status' => [self::simpleSiteData(['status' => -1]), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid only_accepted_banners' =>
                [self::simpleSiteData(['only_accepted_banners' => 1]), Response::HTTP_UNPROCESSABLE_ENTITY],
            'missing url' => [self::simpleSiteData([], 'url'), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid url' => [self::simpleSiteData(['url' => 'example']), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid ad units type' => [
                self::simpleSiteData(['adUnits' => 'adUnits']),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid ad unit, missing name' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit([], 'name')]]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid ad unit, invalid name type' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit(['name' => ['name']])]]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid ad unit, missing size' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit([], 'size')]]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid ad unit, invalid size type' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit(['size' => ['300x250']])]]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid ad unit, invalid size' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit(['size' => 'invalid'])]]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'missing categories' => [self::simpleSiteData([], 'categories'), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid categories type' => [
                self::simpleSiteData(['categories' => 'unknown']),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'not allowed categories' => [
                self::simpleSiteData(['categories' => ['good']]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'missing filtering' => [self::simpleSiteData([], 'filtering'), Response::HTTP_UNPROCESSABLE_ENTITY],
            'invalid filtering' => [self::simpleSiteData(['filtering' => true]), Response::HTTP_UNPROCESSABLE_ENTITY],
            'missing filtering.requires' => [
                self::simpleSiteData(['filtering' => self::filtering([], 'requires')]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid filtering.requires' => [
                self::simpleSiteData(['filtering' => self::filtering(['requires' => 1])]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'missing filtering.excludes' => [
                self::simpleSiteData(['filtering' => self::filtering([], 'excludes')]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid filtering.excludes 1' => [
                self::simpleSiteData(['filtering' => self::filtering(['excludes' => [1]])]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid filtering.excludes 2' => [
                self::simpleSiteData(['filtering' => self::filtering(['excludes' => ['category' => 'unknown']])]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'invalid filtering.excludes 3' => [
                self::simpleSiteData(['filtering' => self::filtering(['excludes' => ['category' => [1]]])]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
        ];
    }

    private static function simpleSiteData(array $mergeData = [], string $remove = null): array
    {
        $siteData = array_merge(
            [
                'status' => 2,
                'name' => 'example.com',
                'url' => 'https://example.com',
                'primaryLanguage' => 'en',
                'medium' => 'web',
                'vendor' => null,
                'onlyAcceptedBanners' => true,
                'filtering' => self::filtering(),
                'adUnits' => [
                    self::simpleAdUnit(),
                ],
                'categories' => [
                    'unknown',
                ],
            ],
            $mergeData
        );

        if ($remove !== null) {
            unset($siteData[$remove]);
        }

        return $siteData;
    }

    private static function filtering(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge(
            [
                'requires' => [],
                'excludes' => [
                    'test_classifier:category' => [
                        'annoying',
                    ],
                ],
            ],
            $mergeData
        );

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    private static function simpleAdUnit(array $mergeData = [], string $remove = null): array
    {
        $adUnit = array_merge(
            [
                'name' => 'Medium Rectangle',
                'type' => 'display',
                'size' => '300x250',
                'label' => 'Medium Rectangle',
                'tags' => [
                    'Desktop',
                    'best',
                ],
                'status' => 1,
                'id' => null,
            ],
            $mergeData
        );

        if ($remove !== null) {
            unset($adUnit[$remove]);
        }

        return $adUnit;
    }

    public function testCreateSiteWithDuplicatedPopUp(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $siteData = self::simpleSiteData();
        $siteData['adUnits'] = [
            self::simpleAdUnit(['size' => 'pop-up']),
            self::simpleAdUnit(['size' => 'pop-up']),
        ];
        $response = $this->postJson(self::URI, ['site' => $siteData]);
        $response->assertStatus(Response::HTTP_CREATED);

        $siteId = $this->getIdFromLocation($response->headers->get('Location'));
        self::assertCount(1, (new Zone())->where('site_id', $siteId)->get());
    }

    public function testCreateSiteWhenOnlyLocalBannersAreAllowed(): void
    {
        Config::updateAdminSettings(
            [Config::SITE_CLASSIFIER_LOCAL_BANNERS => Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY]
        );
        $this->setupUser();
        $siteData = self::simpleSiteData(['onlyAcceptedBanners' => false]);

        $response = $this->postJson(self::URI, ['site' => $siteData]);
        $response->assertStatus(Response::HTTP_CREATED);

        $siteId = $this->getIdFromLocation($response->headers->get('Location'));
        $site = (new Site())->where('id', $siteId)->first();
        self::assertTrue($site->only_accepted_banners);
    }

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdateSite($data): void
    {
        $user = $this->setupUser();
        /** @var  Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => $data]);
        $response->assertStatus(Response::HTTP_OK);

        $this->getJson(self::getSiteUri($site->id))->assertStatus(Response::HTTP_OK)->assertJsonFragment(
            [
                'name' => $data['name'] ?? $site->name,
                'primaryLanguage' => $data['primaryLanguage'] ?? $site->primary_language,
                'status' => $data['status'] ?? $site->status,
            ]
        );
    }

    public function testUpdateSiteUrl(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        $url = 'https://example2.com';

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['url' => $url]]);

        $response->assertStatus(Response::HTTP_OK);
        $this->getJson(self::getSiteUri($site->id))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['url' => $url]);

        $site->refresh();
        self::assertEquals(0, $site->rank);
        self::assertEquals('unknown', $site->info);
    }

    public function testUpdateSiteOnlyAcceptedBanners(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['onlyAcceptedBanners' => true]]);

        $response->assertStatus(Response::HTTP_OK);
        $site->refresh();
        self::assertTrue($site->only_accepted_banners);
    }

    public function testUpdateSiteOnlyAcceptedBannersWhenOnlyLocalAllowed(): void
    {
        Config::updateAdminSettings(
            [Config::SITE_CLASSIFIER_LOCAL_BANNERS => Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY]
        );
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'only_accepted_banners' => 1]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['onlyAcceptedBanners' => false]]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $site->refresh();
        self::assertTrue($site->only_accepted_banners);
    }

    public function testUpdateSiteOnlyAcceptedBannersInvalidType(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'only_accepted_banners' => 1]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['onlyAcceptedBanners' => 1]]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateSiteInvalidUrl(): void
    {
        $user = $this->setupUser();
        /** @var  Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['url' => 'ftp://example']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateSiteRestorePopUp(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(
            [
                'site_id' => $site->id,
                'type' => 'pop',
                'size' => 'pop-up',
                'deleted_at' => new DateTime(),
            ]
        );

        $response = $this->patchJson(
            self::getSiteUri($site->id),
            ['site' => ['adUnits' => [self::simpleAdUnit(['size' => 'pop-up'])]]]
        );
        $response->assertStatus(Response::HTTP_OK);

        $zone->refresh();
        self::assertFalse($zone->trashed(), 'Zone was not restored.');
    }

    public function testUpdateSiteDeletePopUp(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(
            [
                'site_id' => $site->id,
                'type' => 'pop',
                'size' => 'pop-up',
            ]
        );

        $response = $this->patchJson(
            self::getSiteUri($site->id),
            ['site' => ['adUnits' => [self::simpleAdUnit()]]]
        );
        $response->assertStatus(Response::HTTP_OK);

        $zone->refresh();
        self::assertTrue($zone->trashed(), 'Zone was not deleted.');
    }

    public function testUpdateSiteAdZone(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(
            [
                'name' => 'a',
                'site_id' => $site->id,
                'size' => '300x250',
                'type' => 'display',
            ]
        );

        $response = $this->patchJson(
            self::getSiteUri($site->id),
            [
                'site' => [
                    'adUnits' => [
                        [
                            'id' => $zone->id,
                            'name' => 'b',
                            'size' => '300x250',
                            'type' => 'display',
                        ]
                    ]
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_OK);

        $zone->refresh();
        self::assertEquals('b', $zone->name, 'Zone name was not changed');
    }

    public function testUpdateSiteAdZoneDeleted(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(
            [
                'name' => 'a',
                'site_id' => $site->id,
                'size' => '300x250',
                'type' => 'display',
                'deleted_at' => new DateTime(),
            ]
        );

        $response = $this->patchJson(
            self::getSiteUri($site->id),
            [
                'site' => [
                    'adUnits' => [
                        [
                            'id' => $zone->id,
                            'name' => 'b',
                            'size' => '300x250',
                            'type' => 'display',
                        ]
                    ]
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_OK);

        $zone->refresh();
        self::assertEquals('a', $zone->name, 'Deleted zone name was changed');
        $zones = (new Zone())->where('site_id', $site->id)->get();
        self::assertCount(1, $zones);
        self::assertNotEquals($zone->id, $zones->first()->id);
    }

    public function testDeleteSite(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $this->deleteJson(self::getSiteUri($site->id))->assertStatus(Response::HTTP_OK);

        $this->getJson(self::getSiteUri($site->id))->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteSiteWithZones(): void
    {
        $user = $this->setupUser();

        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        Zone::factory()->count(3)->create(['site_id' => $site->id]);

        $this->assertDatabaseHas(
            'zones',
            [
                'site_id' => $site->id,
            ]
        );

        $this->deleteJson(self::getSiteUri($site->id))->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing(
            'sites',
            [
                'id' => $site->id,
                'deleted_at' => null,
            ]
        );

        $this->assertDatabaseMissing(
            'zones',
            [
                'site_id' => $site->id,
                'deleted_at' => null,
            ]
        );

        $this->assertDatabaseMissing(
            'sites',
            [
                'id' => $site->id,
                'deleted_at' => null,
            ]
        );

        $this->assertDatabaseMissing(
            'zones',
            [
                'site_id' => $site->id,
                'deleted_at' => null,
            ]
        );

        $this->getJson(self::getSiteUri($site->id))->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFailDeleteNotOwnedSite(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        /** @var User $user */
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $this->actingAs(User::factory()->create(), 'api');
        $this->deleteJson(self::getSiteUri($site->id))->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function updateDataProvider(): array
    {
        return [
            [
                [
                    "status" => 1,
                    "name" => "name1",
                    "primaryLanguage" => "xx",
                ],
            ],
            [
                [
                    'status' => 1,
                ],
            ],
            [
                [
                    "name" => "name2",
                ],
            ],
            [
                [
                    "primaryLanguage" => "xx",
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider updateZonesInSiteProvider
     */
    public function updateZonesInSite($data): void
    {
        $user = $this->setupUser();

        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        Zone::factory()->count(3)->create(['site_id' => $site->id]);
        $response = $this->getJson(self::getSiteUri($site->id));
        $response->assertJsonCount(3, 'adUnits');

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['adUnits' => $data]]);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->getJson(self::getSiteUri($site->id));
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::SITE_STRUCTURE);
        $response->assertJsonCount(2, 'adUnits');
    }

    public function updateZonesInSiteProvider(): array
    {
        return [
            'completelyNewZones' => [
                [
                    [
                        "status" => 0,
                        "name" => "title1",
                        "size" => '125x125',
                    ],
                    [
                        "status" => 1,
                        "name" => "title2",
                        "size" => '300x250',
                    ],
                ],
            ],
            'oneNewZone' => [
                [
                    [
                        "id" => "1",
                        "status" => 0,
                        "name" => "new-title1",
                        "size" => '125x125',
                    ],

                    [
                        "status" => 1,
                        "name" => "new-title2",
                        "size" => '300x250',
                    ],
                ],
            ],
            'bothOldZones' => [
                [
                    [
                        "id" => "1",
                        "status" => 0,
                        "name" => "new-title1",
                        "size" => '125x125',
                    ],
                    [
                        "id" => "2",
                        "status" => 1,
                        "name" => "new-title2",
                        "size" => '300x250',
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider updateZonesInSiteProvider
     */
    public function failZoneUpdatesInSite($data): void
    {
        $user = $this->setupUser();

        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        Zone::factory()->count(3)->create(['site_id' => $site->id]);
        $response = $this->getJson(self::getSiteUri($site->id));
        $response->assertJsonCount(3, 'adUnits');

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['adUnits' => $data]]);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->getJson(self::getSiteUri($site->id));
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::SITE_STRUCTURE);
        $response->assertJsonCount(2, 'adUnits');
    }

    /**
     * @dataProvider filteringDataProvider
     */
    public function testSiteFiltering(array $data, array $preset): void
    {
        $this->setupUser();
        $postResponse = $this->postJson(self::URI, ['site' => $data]);

        $postResponse->assertStatus(Response::HTTP_CREATED);
        $postResponse->assertHeader('Location');

        $id = $this->getIdFromLocation($postResponse->headers->get('Location'));

        $response = $this->getJson(self::getSiteUri($id));
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure(self::SITE_STRUCTURE)->assertJsonCount(
            2,
            'filtering'
        );

        $content = json_decode($response->content(), true);
        $this->assertEquals($preset['onlyAcceptedBanners'] ?? false, $content['onlyAcceptedBanners']);
    }

    public function filteringDataProvider(): array
    {
        $presets = [
            [],
            [
                "onlyAcceptedBanners" => false,
            ],
            [
                "onlyAcceptedBanners" => true,
            ],
        ];

        $default = json_decode(
            <<<JSON
{
    "filtering": {
      "requires": {},
      "excludes": {}
    },
    "status": 0,
    "name": "nameA",
    "url": "https://example.com",
    "primaryLanguage": "pl",
    "medium": "web",
    "vendor": null,
    "adUnits": [
      {
        "name": "name",
        "size": "300x250"
      }
    ],
    "categories": [
      "unknown"
    ]
  }
JSON
            ,
            true
        );

        return array_map(
            function ($preset) use ($default) {
                return [array_merge($default, $preset), $preset];
            },
            $presets
        );
    }

    /**
     * @dataProvider verifyDomainProvider
     *
     * @param array $data
     * @param int $expectedStatus
     * @param string $expectedMessage
     */
    public function testVerifyDomain(array $data, int $expectedStatus, string $expectedMessage): void
    {
        $this->setupUser();
        SitesRejectedDomain::factory()->create(['domain' => 'rejected.com']);

        $response = $this->postJson(self::URI_DOMAIN_VERIFY, $data);
        $response->assertStatus($expectedStatus)->assertJsonStructure(self::DOMAIN_VERIFY_STRUCTURE);
        self::assertEquals($expectedMessage, $response->json('message'));
    }

    public function verifyDomainProvider(): array
    {
        return [
            [['invalid' => 1], Response::HTTP_BAD_REQUEST, 'Field `domain` is required.'],
            [['domain' => 1], Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid domain.'],
            [
                ['domain' => 'example.rejected.com'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The subdomain example.rejected.com is not supported. Please use your own domain.',
            ],
            [['domain' => 'rejected.com'], Response::HTTP_OK, 'Valid domain.'],
            [['domain' => 'example.com'], Response::HTTP_OK, 'Valid domain.'],
        ];
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function testSiteCodesConfirm(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email_confirmed_at' => null, 'admin_confirmed_at' => null]);
        $this->actingAs($user, 'api');
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/sites/' . $site->id . '/codes');
        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $user->email_confirmed_at = new DateTimeImmutable('-1 hour');
        $user->admin_confirmed_at = null;
        $user->saveOrFail();

        $response = $this->getJson('/api/sites/' . $site->id . '/codes');
        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $user->email_confirmed_at = null;
        $user->admin_confirmed_at = new DateTimeImmutable('-1 hour');
        $user->saveOrFail();

        $response = $this->getJson('/api/sites/' . $site->id . '/codes');
        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $user->email_confirmed_at = new DateTimeImmutable('-1 hour');
        $user->admin_confirmed_at = new DateTimeImmutable('-1 hour');
        $user->saveOrFail();

        $response = $this->getJson('/api/sites/' . $site->id . '/codes');
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testSiteSizes(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $sizes = ['300x250', '336x280', '728x90'];
        foreach ($sizes as $size) {
            Zone::factory()->create(['site_id' => $site->id, 'size' => $size]);
        }

        $response = $this->getJson('/api/sites/sizes/' . $site->id);

        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure(self::SIZES_STRUCTURE);
        self::assertEquals($sizes, $response->json('sizes'));
    }

    public function testSiteRank(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(
            [
                'user_id' => $user->id,
                'rank' => 0.2,
                'info' => AdUser::PAGE_INFO_LOW_CTR,
            ]
        );

        $response = $this->getJson('/api/sites/' . $site->id . '/rank');

        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure(self::RANK_STRUCTURE);
        self::assertEquals(0.2, $response->json('rank'));
        self::assertEquals(AdUser::PAGE_INFO_LOW_CTR, $response->json('info'));
    }

    public function testChangeStatus(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'status' => Site::STATUS_ACTIVE]);

        $this->putJson('/api/sites/' . $site->id . '/status', ['site' => ['status' => Site::STATUS_INACTIVE]])
            ->assertStatus(Response::HTTP_OK);
        $site->refresh();
        self::assertEquals(Site::STATUS_INACTIVE, $site->status);
    }

    public function testChangeStatusInvalid(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $this->putJson('/api/sites/' . $site->id . '/status', ['site' => ['status' => -1]])
            ->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testChangeStatusMissing(): void
    {
        $user = $this->setupUser();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $this->putJson('/api/sites/' . $site->id . '/status', ['site' => ['stat' => -1]])
            ->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testGetCryptovoxelsCode(): void
    {
        /** @var User $user */
        $user = User::factory()->create(
            [
                'admin_confirmed_at' => new DateTime(),
                'email_confirmed_at' => new DateTime(),
                'wallet_address' => new WalletAddress('ads', '0001-00000001-8B4E'),
            ]
        );
        $this->actingAs($user, 'api');

        $response = $this->get(self::URI_CRYPTOVOXELS_CODE);
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testGetCryptovoxelsCodeUserNotConfirmed(): void
    {
        /** @var User $user */
        $user = User::factory()->create(
            [
                'wallet_address' => new WalletAddress('ads', '0001-00000001-8B4E'),
            ]
        );
        $this->actingAs($user, 'api');

        $response = $this->get(self::URI_CRYPTOVOXELS_CODE);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testGetCryptovoxelsCodeWalletNotConnected(): void
    {
        /** @var User $user */
        $user = User::factory()->create(
            [
                'admin_confirmed_at' => new DateTime(),
                'email_confirmed_at' => new DateTime(),
            ]
        );
        $this->actingAs($user, 'api');

        $response = $this->get(self::URI_CRYPTOVOXELS_CODE);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(
            AdUser::class,
            static function () {
                return new DummyAdUserClient();
            }
        );

        $this->instance(ConfigurationRepository::class, new DummyConfigurationRepository());
    }

    private function setupUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        return $user;
    }
}
