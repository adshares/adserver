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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Mail\SiteApprovalPending;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SiteRejectReason;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use DateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

    public function testCreateSiteWhileAcceptanceRequired(): void
    {
        $this->login();
        Config::updateAdminSettings([Config::SITE_APPROVAL_REQUIRED => '*']);

        $response = $this->postJson(self::URI, ['site' => self::simpleSiteData()]);

        $response->assertStatus(Response::HTTP_CREATED);
        $id = $this->getIdFromLocation($response->headers->get('Location'));

        self::assertDatabaseHas(Site::class, [
            'id' => $id,
            'status' => Site::STATUS_PENDING_APPROVAL,
        ]);
        Mail::assertQueued(SiteApprovalPending::class);
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

        $default = [
            'filtering' => [
                'requires' => [
                    'category' => [
                        '1'
                    ]
                ],
                'excludes' => [],
            ],
            'onlyAcceptedBanners' => false,
            'adUnits' => [
                [
                    'name' => 'ssss',
                    'size' => '300x250'
                ]
            ],
            'categories' => [
                'unknown'
            ]
        ];

        return array_map(
            function ($preset) use ($default) {
                return [array_merge($default, $preset), $preset];
            },
            $presets
        );
    }

    public function testCreateSiteWithAdUnits(): void
    {
        $this->login();
        $siteData = self::simpleSiteData([
            'adUnits' => [
                self::simpleAdUnit(['size' => '300x250']),
                self::simpleAdUnit(['size' => 'pop-up']),
            ]
        ]);
        $response = $this->postJson(self::URI, ['site' => $siteData]);

        $response->assertStatus(Response::HTTP_CREATED);
        self::assertDatabaseHas(Zone::class, ['size' => '300x250', 'type' => Zone::TYPE_DISPLAY]);
        self::assertDatabaseHas(Zone::class, ['size' => 'pop-up', 'type' => Zone::TYPE_POP]);
    }

    public function testCreateSiteWhileExist(): void
    {
        $user = $this->login();
        Site::factory()->create(['user_id' => $user]);

        $response = $this->postJson(self::URI, ['site' => self::simpleSiteData()]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider createSiteUnprocessableProvider
     *
     * @param array $siteData
     */
    public function testCreateSiteUnprocessable(array $siteData): void
    {
        $this->setupUser();

        $response = $this->postJson(self::URI, ['site' => $siteData]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function createSiteUnprocessableProvider(): array
    {
        return [
            'no data' => [[]],
            'missing name' => [self::simpleSiteData([], 'name')],
            'missing language' => [self::simpleSiteData([], 'primaryLanguage')],
            'invalid language' => [self::simpleSiteData(['primaryLanguage' => 'English'])],
            'missing status' => [self::simpleSiteData([], 'status')],
            'invalid status' => [self::simpleSiteData(['status' => -1])],
            'invalid only_accepted_banners' => [self::simpleSiteData(['only_accepted_banners' => 1])],
            'missing url' => [self::simpleSiteData([], 'url')],
            'invalid url' => [self::simpleSiteData(['url' => 'example'])],
            'invalid ad units type' => [self::simpleSiteData(['adUnits' => 'adUnits'])],
            'invalid ad unit, missing name' => [self::simpleSiteData(['adUnits' => [self::simpleAdUnit([], 'name')]])],
            'invalid ad unit, invalid name type' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit(['name' => ['name']])]]),
            ],
            'invalid ad unit, missing size' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit([], 'size')]]),
            ],
            'invalid ad unit, invalid size type' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit(['size' => ['300x250']])]]),
            ],
            'invalid ad unit, invalid size' => [
                self::simpleSiteData(['adUnits' => [self::simpleAdUnit(['size' => 'invalid'])]]),
            ],
            'missing categories' => [self::simpleSiteData([], 'categories')],
            'invalid categories type' => [self::simpleSiteData(['categories' => 'unknown'])],
            'not allowed categories' => [self::simpleSiteData(['categories' => ['good']])],
            'missing filtering' => [self::simpleSiteData([], 'filtering')],
            'invalid filtering' => [self::simpleSiteData(['filtering' => true])],
            'missing filtering.requires' => [
                self::simpleSiteData(['filtering' => self::filtering([], 'requires')]),
            ],
            'invalid filtering.requires' => [
                self::simpleSiteData(['filtering' => self::filtering(['requires' => 1])]),
            ],
            'missing filtering.excludes' => [
                self::simpleSiteData(['filtering' => self::filtering([], 'excludes')]),
            ],
            'invalid filtering.excludes 1' => [
                self::simpleSiteData(['filtering' => self::filtering(['excludes' => [1]])]),
            ],
            'invalid filtering.excludes 2' => [
                self::simpleSiteData(['filtering' => self::filtering(['excludes' => ['category' => 'unknown']])]),
            ],
            'invalid filtering.excludes 3' => [
                self::simpleSiteData(['filtering' => self::filtering(['excludes' => ['category' => [1]]])]),
            ],
            'invalid medium' => [
                self::simpleSiteData(['medium' => 'invalid']),
            ],
            'invalid vendor' => [
                self::simpleSiteData(['vendor' => 'invalid']),
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
        Config::updateAdminSettings([Config::SITE_APPROVAL_REQUIRED => '*']);
        $user = $this->login();
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
        self::assertNull($site->accepted_at);
        self::assertEquals(Site::STATUS_PENDING_APPROVAL, $site->status);
    }

    public function testUpdateSiteUrlFailWhenExists(): void
    {
        $user = $this->setupUser();
        Site::factory()->create([
            'domain' => 'example2.com',
            'url' => 'https://example2.com',
            'user_id' => $user->id,
        ]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        $url = 'https://example2.com';

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['url' => $url]]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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

    public function testUpdateSiteFailWhileInvalidStatus(): void
    {
        $user = $this->setupUser();
        /** @var  Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['status' => 100]]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateSiteFailWhilePendingApproval(): void
    {
        $user = $this->setupUser();
        /** @var  Site $site */
        $site = Site::factory()->create(['status' => Site::STATUS_PENDING_APPROVAL, 'user_id' => $user]);

        $response = $this->patchJson(self::getSiteUri($site->id), ['site' => ['status' => Site::STATUS_ACTIVE]]);
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
            'status, name, language' => [
                [
                    "status" => 1,
                    "name" => "name1",
                    "primaryLanguage" => "xx",
                ],
            ],
            'status' => [
                [
                    'status' => 1,
                ],
            ],
            'name' => [
                [
                    "name" => "name2",
                ],
            ],
            'language' => [
                [
                    "primaryLanguage" => "xx",
                ],
            ],
        ];
    }

    /**
     * @dataProvider updateZonesInSiteProvider
     */
    public function testUpdateZonesInSite($data): void
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
     * @dataProvider updateZonesInSiteProvider
     */
    public function testFailZoneUpdatesInSite($data): void
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
        $response->assertJsonPath('onlyAcceptedBanners', $preset['onlyAcceptedBanners'] ?? false);
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

        $default = [
            'filtering' => [
                'requires' => [],
                'excludes' => []
            ],
            'status' => 0,
            'name' => 'nameA',
            'url' => 'https://example.com',
            'primaryLanguage' => 'pl',
            'medium' => 'web',
            'vendor' => null,
            'adUnits' => [
                [
                    'name' => 'name',
                    'size' => '300x250'
                ]
            ],
            'categories' => [
                'unknown'
            ],
        ];

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
        $this->login();
        SitesRejectedDomain::factory()->create(['domain' => 'rejected.com']);

        $response = $this->postJson(self::URI_DOMAIN_VERIFY, $data);
        $response->assertStatus($expectedStatus)->assertJsonStructure(self::DOMAIN_VERIFY_STRUCTURE);
        self::assertEquals($expectedMessage, $response->json('message'));
    }

    public function verifyDomainProvider(): array
    {
        return [
            [['invalid' => 1], Response::HTTP_BAD_REQUEST, 'Field `domain` is required.'],
            [['domain' => 1, 'medium' => 'web'], Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid domain.'],
            [
                ['domain' => 'example.rejected.com', 'medium' => 'web'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The domain example.rejected.com is rejected.',
            ],
            [['domain' => 'example.com', 'medium' => 'web'], Response::HTTP_OK, 'Valid domain.'],
            [['domain' => 'example.com'], Response::HTTP_UNPROCESSABLE_ENTITY, 'Field `medium` is required.'],
            [
                ['domain' => 'example.com', 'medium' => 0],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Field `medium` must be a string.',
            ],
            [
                ['domain' => 'example.com', 'medium' => 'web', 'vendor' => 0],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Field `vendor` must be a string or null.',
            ],
            [
                ['domain' => 'example.com', 'medium' => 'metaverse', 'vendor' => 'decentraland'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid domain example.com.',
            ],
            [
                ['domain' => 'example.com', 'medium' => 'metaverse', 'vendor' => 'cryptovoxels'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid domain example.com.',
            ],
            [
                ['domain' => 'scene-2-n5.decentraland.org', 'medium' => 'metaverse', 'vendor' => 'decentraland'],
                Response::HTTP_OK,
                'Valid domain.',
            ],
            [
                ['domain' => 'scene-4745.cryptovoxels.com', 'medium' => 'metaverse', 'vendor' => 'cryptovoxels'],
                Response::HTTP_OK,
                'Valid domain.',
            ],
        ];
    }

    public function testVerifyDomainWhileDomainRejectedWithReason(): void
    {
        $this->login();
        SitesRejectedDomain::factory()->create([
            'domain' => 'rejected.com',
            'reject_reason_id' => SiteRejectReason::factory()->create(),
        ]);
        $response = $this->postJson(self::URI_DOMAIN_VERIFY, ['domain' => 'example.rejected.com', 'medium' => 'web']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure(self::DOMAIN_VERIFY_STRUCTURE);
        self::assertEquals(
            'The domain example.rejected.com is rejected. Reason: Test reject reason',
            $response->json('message'),
        );
    }

    public function testVerifyDomainWhileDomainRejectedWithInvalidReason(): void
    {
        Log::spy();
        $this->login();
        SitesRejectedDomain::factory()->create([
            'domain' => 'rejected.com',
            'reject_reason_id' => 1500,
        ]);
        $response = $this->postJson(self::URI_DOMAIN_VERIFY, ['domain' => 'example.rejected.com', 'medium' => 'web']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure(self::DOMAIN_VERIFY_STRUCTURE);
        self::assertEquals(
            'The domain example.rejected.com is rejected.',
            $response->json('message'),
        );
        Log::shouldHaveReceived('warning')
            ->with('Cannot find reject reason (site_reject_reasons) with id (1500)')
            ->once();
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

    public function testSiteCodesFailWhileSiteIsPending(): void
    {
        $user = $this->login(User::factory()->create([
            'admin_confirmed_at' => new DateTimeImmutable('-10 days'),
            'email_confirmed_at' => new DateTimeImmutable('-10 days'),
        ]));
        /** @var Site $site */
        $site = Site::factory()->create([
            'status' => Site::STATUS_PENDING_APPROVAL,
            'user_id' => $user,
        ]);

        $response = $this->getJson('/api/sites/' . $site->id . '/codes');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testReadSitesSizes(): void
    {
        $user = $this->login();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user]);
        $expectedSizes = ['300x250', '336x280', '728x90', '4096x4096', '2048x2048', '1024x1024', '512x512', '480x640',
            '640x480', '3072x4096', '4096x3072', '1536x2048', '2048x1536', '768x1024', '1024x768'];
        foreach (['300x250', '336x280', '728x90'] as $size) {
            Zone::factory()->create(['scopes' => [$size], 'site_id' => $site, 'size' => $size]);
        }
        Zone::factory([
            'scopes' => ['4096x4096', '2048x2048', '1024x1024', '512x512', '480x640', '640x480', '3072x4096',
                '4096x3072', '1536x2048', '2048x1536', '768x1024', '1024x768'],
            'site_id' => $site,
            'size' => '10x10',
        ])->create();

        $response = $this->getJson('/api/sites/sizes/' . $site->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::SIZES_STRUCTURE);
        self::assertEqualsCanonicalizing($expectedSizes, $response->json('sizes'));
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

    private function setupUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        return $user;
    }
}
