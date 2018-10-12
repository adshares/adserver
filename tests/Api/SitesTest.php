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

namespace Adshares\Adserver\Tests\Feature;

use Adshares\Adserver\Models\Site;
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

        $response = $this->getJson(self::URI.'/1');
        $response->assertStatus(404);
    }

    public function testCreateSite()
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        /* @var $site Site */
        $site = factory(Site::class)->make();

        $response = $this->postJson(self::URI, ['site' => $site->getAttributes()]);

        $response->assertStatus(201);
        $response->assertHeader('Location');
        $response->assertJsonFragment(['name' => $site->name]);
//        $response->assertJsonFragment(['url' => $site->url]);

        $uri = $response->headers->get('Location');
        $matches = [];
        $this->assertTrue(1 === preg_match('/(\d+)$/', $uri, $matches));

        $response = $this->getJson(self::URI.'/'.$matches[1]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $site->name]);
//        $response->assertJsonFragment(['url' => $site->url]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function testCreateSites()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $count = 10;

        $users = factory(Site::class, $count)->make();
        foreach ($users as $site) {
            $response = $this->postJson(self::URI, ['site' => $site->getAttributes()]);
            $response->assertStatus(201);
        }

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount($count);
    }
}
