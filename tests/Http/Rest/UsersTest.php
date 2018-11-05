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

class UsersTest extends TestCase
{
    use RefreshDatabase;

    const URI_AUTH = '/auth/users';
    const URI = '/panel/users';

    public function testCreateUser()
    {
        $this->markTestSkipped('No admin user creation at this time');

        /* @var $user User */
        $user = factory(User::class)->make();

        $response = $this->postJson(self::URI_AUTH, ['user' => $user->getAttributes(), 'uri' => '/']);

        $response->assertStatus(201);
        $response->assertHeader('Location');
        $response->assertJsonFragment(['email' => $user->email]);

        $uri = $response->headers->get('Location');
        $matches = [];
        $this->assertTrue(1 === preg_match('/(\d+)$/', $uri, $matches));

        $this->actingAs(factory(User::class)->create(['is_admin' => true]), 'api');

        $response = $this->getJson(self::URI . '/' . $matches[1]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $user->email]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function testCreateUsers()
    {
        $this->markTestSkipped('No admin user creation at this time');
        $count = 10;

        $users = factory(User::class, $count)->make();
        foreach ($users as $user) {
            $response = $this->postJson(self::URI, ['user' => $user->getAttributes(), 'uri' => '/']);
            $response->assertStatus(201);
        }

        $this->actingAs(factory(User::class)->create(['is_admin' => true]));

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount($count);
    }
}
