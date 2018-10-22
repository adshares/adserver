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
    private const URI = '/api/users';

    public function testCreateUser()
    {
        /* @var $user User */
        $user = factory(User::class)->make();

        $this->actingAs(factory(User::class)->create(["is_admin" => true]), 'api');
        $data = [
            'user' => [
                'email' => $user->email,
                'password' => 'password',
                'isAdvertiser' => true,
                'isPublisher' => true,
            ],
        ];
        $response = $this->postJson('/api/users', $data);

        $response->assertStatus(201);
        $response->assertHeader('Location');
        $response->assertJsonFragment(['email' => $user->email]);

        $uri = $response->headers->get('Location');
        $matches = [];
        $this->assertSame(1, preg_match('/(\d+)$/', $uri, $matches));

        $this->actingAs(factory(User::class)->create(['is_admin' => true]), 'api');

        $response = $this->getJson("/api/users/{$matches[1]}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $user->email]);
    }
}
