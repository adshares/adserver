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

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SitesTest extends TestCase
{
    use RefreshDatabase;
    const URI = '/api/sites';

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
    public function testCreateSite($data)
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, $data);

        $response->assertStatus(201);
        $response->assertHeader('Location');

        $uri = $response->headers->get('Location');
        $matches = [];
        $this->assertTrue(1 === preg_match('/(\d+)$/', $uri, $matches));

        $response = $this->getJson(self::URI . '/' . $matches[1]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $data['site']['name']]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function creationDataProvider(): array
    {
        return [
            [
                json_decode(<<<JSON
{"site":{"id":0,"status":2,"name":"sss","primaryLanguage":0,"filtering":{"requires":{"category":["1"]},"excludes":{}},"adUnits":[{"shortHeadline":"ssss","type":0,"size":{"id":3,"name":"Large Rectangle","type":"large-rectangle","size":2,"tags":["Desktop","best"],"width":"336","height":"280","selected":true}}]}}
JSON
                ,true),
            ],
        ];
    }
}
