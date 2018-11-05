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

namespace Adshares\Adserver\Tests\Http\Rest;

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SitesTest extends TestCase
{
    use RefreshDatabase;
    private const URI = '/api/sites';
    const SITE_STRUCTURE = [
        'id',
        'name',
        'filtering',
        'adUnits' => ['*' => ['shortHeadline', 'code', 'size', 'status']],
        'status',
        'primaryLanguage',
    ];
    const BASIC_SITE_STRUCTURE = [
        'id',
        'name',
        'status',
        'primaryLanguage',
        'filtering',
        'adUnits',
    ];

    public function testEmptyDb()
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::URI . '/1');
        $response->assertStatus(404);
    }

    /**
     * @dataProvider creationDataProvider
     */
    public function testCreateSite($data, $preset)
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, ['site' => $data]);

        $response->assertStatus(201);
        $response->assertHeader('Location');

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(200)
            ->assertJsonStructure(self::SITE_STRUCTURE)
            ->assertJsonFragment([
                'name' => $preset['name'],
                'primaryLanguage' => $preset['primaryLanguage'],
            ])
            ->assertJsonCount(1, 'adUnits')
            ->assertJsonCount(2, 'filtering')
            ->assertJsonCount(1, 'filtering.requires')
            ->assertJsonCount(0, 'filtering.excludes');
    }

    public function testCreateMultipleSites()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        array_map(function () use ($user) {
            factory(Site::class)->create(['user_id' => $user->id]);
        }, $this->creationDataProvider());

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            '*' => self::SITE_STRUCTURE,
        ]);
    }

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdateSite($data)
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::URI . "/{$site->id}", ['site' => $data]);
        $response->assertStatus(204);

        $this->getJson(self::URI . "/{$site->id}")
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => $data['name'] ?? $site->name,
                'primaryLanguage' => $data['primaryLanguage'] ?? $site->primary_language,
                'status' => $data['status'] ?? $site->status,
            ]);
    }

    public function testDeleteSite(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $this->deleteJson(self::URI . "/{$site->id}")
            ->assertStatus(200);

        $this->getJson(self::URI . "/{$site->id}")
            ->assertStatus(404);
    }

    public function testDeleteSiteWithZones(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $site = factory(Site::class)
            ->create(['user_id' => $user->id]);
        $site->zones(factory(Zone::class, 3)->create(['site_id' => $site->id]));

        $this->assertDatabaseHas('zones', [
            'site_id' => $site->id,
        ]);

        $this->deleteJson(self::URI . "/{$site->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('sites', [
            'id' => $site->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseMissing('zones', [
            'site_id' => $site->id,
            'deleted_at' => null,
        ]);

        $this->getJson(self::URI . "/{$site->id}")
            ->assertStatus(404);
    }

    public function testFailDeleteNotOwnedSite(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $user = factory(User::class)->create();
        $site = factory(Site::class)->create(['user_id' => $user->id]);

        $this->actingAs(factory(User::class)->create(), 'api');
        $this->deleteJson(self::URI . "/{$site->id}")
            ->assertStatus(404);
    }

    public function updateDataProvider(): array
    {
        return [
            [
                [
                    "status" => "1",
                    "name" => "name" . rand(),
                    "primaryLanguage" => "xx",
                ],
            ],
            [
                [
                    'status' => "1",
                ],
            ],
            [
                [
                    "name" => "name" . rand(),
                ],
            ],
            [
                [
                    "primaryLanguage" => "xx",
                ],
            ],
        ];
    }

    public function creationDataProvider(): array
    {
        $presets = [
            [
                "status" => 0,
                "name" => "name" . rand(),
                "primaryLanguage" => "pl",
            ],
            [
                'status' => "1",
                "name" => "name" . rand(),
                "primaryLanguage" => "en",
            ],
        ];

        $default =
            json_decode(<<<JSON
{
    "filtering": {
      "requires": {
        "category": [
          "1"
        ]
      },
      "excludes": {}
    },
    "adUnits": [
      {
        "shortHeadline": "ssss",
        "type": 0,
        "size": {
          "id": 3,
          "name": "Large Rectangle",
          "type": "large-rectangle",
          "size": 2,
          "tags": [
            "Desktop",
            "best"
          ],
          "width": "336",
          "height": "280",
          "selected": true
        }
      }
    ]
  }
JSON
                , true);

        return
            array_map(function ($preset) use ($default) {
                return [array_merge($default, $preset), $preset];
            }, $presets);
    }

    private function getIdFromLocation($location)
    {
        $matches = [];
        $this->assertSame(1, preg_match('/(\d+)$/', $location, $matches));

        return $matches[1];
    }
}
