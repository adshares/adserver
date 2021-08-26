<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\Supply\SiteFilteringUpdater;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Client\DummyAdUserClient;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

use function GuzzleHttp\json_decode;

class SitesControllerTest extends TestCase
{
    private const URI = '/api/sites';

    private const URI_DOMAIN_VERIFY = '/api/sites/domain/validate';

    private const SITE_STRUCTURE = [
        'id',
        'name',
        'domain',
        'url',
        'filtering',
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

    private const BASIC_SITE_STRUCTURE = [
        'id',
        'name',
        'status',
        'primaryLanguage',
        'filtering',
        'adUnits',
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

    public function testEmptyDb(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::URI . '/1');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider creationDataProvider
     */
    public function testCreateSite($data, $preset): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, ['site' => $data]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
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
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        array_map(
            function () use ($user) {
                factory(Site::class)->create(['user_id' => $user->id]);
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
                "status" => 0,
                "name" => "nameA",
                "url" => "https://example.com",
                "primaryLanguage" => "pl",
            ],
            [
                'status' => 1,
                "name" => "nameB",
                "url" => "https://example.com",
                "primaryLanguage" => "en",
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
    "requireClassified": false,
    "excludeUnclassified": false,
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

    public function testCreateSiteError(): void
    {
        $siteClassificationUpdater = $this->createMock(SiteFilteringUpdater::class);
        $siteClassificationUpdater->method('addClassificationToFiltering')
            ->willThrowException(new RuntimeException('test-exception'));
        $this->instance(SiteFilteringUpdater::class, $siteClassificationUpdater);
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, ['site' => self::simpleSiteData()]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @dataProvider createSiteUnprocessableProvider
     *
     * @param array $siteData
     * @param int $expectedStatus
     */
    public function testCreateSiteUnprocessable(array $siteData, int $expectedStatus): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

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
                'requireClassified' => false,
                'excludeUnclassified' => true,
                'filtering' => [
                    'requires' => [],
                    'excludes' => [
                        'test_classifier:category' => [
                            'annoying',
                        ],
                    ],
                ],
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

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdateSite($data): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::URI . "/{$site->id}", ['site' => $data]);
        $response->assertStatus(Response::HTTP_OK);

        $this->getJson(self::URI . "/{$site->id}")->assertStatus(Response::HTTP_OK)->assertJsonFragment(
            [
                'name' => $data['name'] ?? $site->name,
                'primaryLanguage' => $data['primaryLanguage'] ?? $site->primary_language,
                'status' => $data['status'] ?? $site->status,
            ]
        );
    }

    public function testDeleteSite(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $this->deleteJson(self::URI . "/{$site->id}")->assertStatus(Response::HTTP_OK);

        $this->getJson(self::URI . "/{$site->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteSiteWithZones(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $site = factory(Site::class)->create(['user_id' => $user->id]);
        $site->zones(factory(Zone::class, 3)->create(['site_id' => $site->id]));

        $this->assertDatabaseHas(
            'zones',
            [
                'site_id' => $site->id,
            ]
        );

        $this->deleteJson(self::URI . "/{$site->id}")->assertStatus(Response::HTTP_OK);

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

        $this->getJson(self::URI . "/{$site->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFailDeleteNotOwnedSite(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $user = factory(User::class)->create();
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $this->actingAs(factory(User::class)->create(), 'api');
        $this->deleteJson(self::URI . "/{$site->id}")->assertStatus(Response::HTTP_NOT_FOUND);
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
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $site = factory(Site::class)->create(['user_id' => $user->id]);
        $site->zones(
            factory(Zone::class, 3)->create(['site_id' => $site->id])
        );
        $response = $this->getJson(self::URI . "/{$site->id}");
        $response->assertJsonCount(3, 'adUnits');

        $response = $this->patchJson(self::URI . "/{$site->id}", ['site' => ['adUnits' => $data]]);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->getJson(self::URI . "/{$site->id}");
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
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $site = factory(Site::class)->create(['user_id' => $user->id]);
        $site->zones(
            factory(Zone::class, 3)->create(['site_id' => $site->id])
        );
        $response = $this->getJson(self::URI . "/{$site->id}");
        $response->assertJsonCount(3, 'adUnits');

        $response = $this->patchJson(self::URI . "/{$site->id}", ['site' => ['adUnits' => $data]]);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->getJson(self::URI . "/{$site->id}");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::SITE_STRUCTURE);
        $response->assertJsonCount(2, 'adUnits');
    }

    /**
     * @dataProvider filteringDataProvider
     */
    public function testSiteFiltering(array $data, array $preset): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $postResponse = $this->postJson(self::URI, ['site' => $data]);

        $postResponse->assertStatus(Response::HTTP_CREATED);
        $postResponse->assertHeader('Location');

        $id = $this->getIdFromLocation($postResponse->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure(self::SITE_STRUCTURE)->assertJsonCount(
            2,
            'filtering'
        );
    }

    public function filteringDataProvider(): array
    {
        $presets = [
            [],
            [
                "requireClassified" => false,
                "excludeUnclassified" => false,
            ],
            [
                "requireClassified" => true,
                "excludeUnclassified" => false,
            ],
            [
                "requireClassified" => false,
                "excludeUnclassified" => true,
            ],
            [
                "requireClassified" => true,
                "excludeUnclassified" => true,
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
        $this->actingAs(factory(User::class)->create(), 'api');
        SitesRejectedDomain::upsert('rejected.com');

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

    public function testSiteCodesConfirm(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create(['email_confirmed_at' => null, 'admin_confirmed_at' => null]);
        $this->actingAs($user, 'api');
        /** @var Site $site */
        $site = factory(Site::class)->create(['user_id' => $user->id]);

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
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        /** @var Site $site */
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $sizes = ['300x250', '336x280', '728x90'];
        foreach ($sizes as $size) {
            factory(Zone::class)->create(['site_id' => $site->id, 'size' => $size]);
        }

        $response = $this->getJson('/api/sites/sizes/' . $site->id);

        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure(self::SIZES_STRUCTURE);
        self::assertEquals($sizes, $response->json('sizes'));
    }

    public function testSiteRank(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        /** @var Site $site */
        $site = factory(Site::class)->create(
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
}
