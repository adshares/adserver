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
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SitesTest extends TestCase
{
    use RefreshDatabase;
    const URI = '/api/sites';
    const SiteStructure = [
        'id',
        'name',
        'filtering',
        'adUnits' => ['*' => ['shortHeadline']],
        'status',
        'primaryLanguage',
    ];
    const BasicSiteStructure = [
        'id',
        'name',
//        'filtering',
//        'adUnits',
        'status',
        'primaryLanguage',
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

        $this->assertResourceData($preset, $this->getIdFromLocation($response->headers->get('Location')));
    }

    public function testMultipleSites()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        array_map(function ($data) use ($user) {
            factory(Site::class)->create(['user_id' => $user->id]);
        }, $this->creationDataProvider());

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            '*' => self::SiteStructure,
        ]);
    }

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdateSite($data)
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        factory(Site::class)->create(['user_id' => $user->id]);

        $response = $this->patchJson(self::URI . '/1', ['site' => $data]);
        $response->assertStatus(204);

        $this->getJson(self::URI . '/1')
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => $data['name'],
                'primaryLanguage' => $data['primaryLanguage'],
                'status' => $data['status'],
            ]);
    }

    public function updateDataProvider()
    {
        return [
            [
                [
                    "status" => "0",
                    "name" => "name" . rand(),
                    "primaryLanguage" => "pl",
                ],
            ],
            [
                [
                    'status' => "1",
                    "name" => "name" . rand(),
                    "primaryLanguage" => "en",
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
                'status' => 1,
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

    private function assertResourceData($preset, $id): void
    {
        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(200)
            ->assertJsonStructure(self::SiteStructure)->assertJsonFragment([
                'name' => $preset['name'],
                'primaryLanguage' => $preset['primaryLanguage'],
            ])
            ->assertJsonCount(2, 'filtering')
            ->assertJsonCount(1, 'filtering.requires')
            ->assertJsonCount(0, 'filtering.excludes');
    }
}
