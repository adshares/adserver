<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Mock\Client\DummyAdUserClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;

class SitesControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/sites';

    private const URI_DOMAIN_VERIFY = '/api/sites/domain/verify';

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

    public function testEmptyDb()
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::URI.'/1');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider creationDataProvider
     */
    public function testCreateSite($data, $preset)
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, ['site' => $data]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI.'/'.$id);
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

    private function getIdFromLocation($location)
    {
        $matches = [];
        $this->assertSame(1, preg_match('/(\d+)$/', $location, $matches));

        return $matches[1];
    }

    public function testCreateMultipleSites()
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
     * @dataProvider updateDataProvider
     */
    public function testUpdateSite($data)
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::URI."/{$site->id}", ['site' => $data]);
        $response->assertStatus(Response::HTTP_OK);

        $this->getJson(self::URI."/{$site->id}")->assertStatus(Response::HTTP_OK)->assertJsonFragment(
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

        $this->deleteJson(self::URI."/{$site->id}")->assertStatus(Response::HTTP_OK);

        $this->getJson(self::URI."/{$site->id}")->assertStatus(Response::HTTP_NOT_FOUND);
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

        $this->deleteJson(self::URI."/{$site->id}")->assertStatus(Response::HTTP_OK);

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

        $this->getJson(self::URI."/{$site->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFailDeleteNotOwnedSite(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $user = factory(User::class)->create();
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $this->actingAs(factory(User::class)->create(), 'api');
        $this->deleteJson(self::URI."/{$site->id}")->assertStatus(Response::HTTP_NOT_FOUND);
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
        $response = $this->getJson(self::URI."/{$site->id}");
        $response->assertJsonCount(3, 'adUnits');

        $response = $this->patchJson(self::URI."/{$site->id}", ['site' => ['adUnits' => $data]]);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->getJson(self::URI."/{$site->id}");
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
        $response = $this->getJson(self::URI."/{$site->id}");
        $response->assertJsonCount(3, 'adUnits');

        $response = $this->patchJson(self::URI."/{$site->id}", ['site' => ['adUnits' => $data]]);
        $response->assertStatus(Response::HTTP_OK);

        $response = $this->getJson(self::URI."/{$site->id}");
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

        $response = $this->getJson(self::URI.'/'.$id);
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
            [['domain' => 'example.com'], Response::HTTP_OK, 'Valid domain.'],
        ];
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
    }
}
