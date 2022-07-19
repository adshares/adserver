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
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

final class ServerConfigurationControllerTest extends TestCase
{
    private const URI_CONFIG = '/api/config';

    public function testAccessAdminNoJwt(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->getJson(self::URI_CONFIG);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserNoJwt(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->getJson(self::URI_CONFIG);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserJwt(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(
            self::URI_CONFIG,
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)]
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFetch(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->getJson(
            self::URI_CONFIG,
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($admin)]
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['support-email', 'technical-email']);
    }

    public function testStoreSingle(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->putJson(
            self::URI_CONFIG . '/support-email',
            ['value' => 'sup@example.com'],
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($admin)]
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(Config::class, ['value' => 'sup@example.com']);
    }

    public function testStore(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->patchJson(
            self::URI_CONFIG,
            [
                'support-email' => 'sup@example.com',
                'technical-email' => 'tech@example.com',
            ],
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($admin)]
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(Config::class, ['value' => 'sup@example.com']);
        self::assertDatabaseHas(Config::class, ['value' => 'tech@example.com']);
    }

    /**
     * @dataProvider storeInvalidDataProvider
     */
    public function testStoreInvalidData(array $data): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->patchJson(
            self::URI_CONFIG,
            $data,
            ['Authorization' => 'Bearer ' . JWTAuth::fromUser($admin)]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function storeInvalidDataProvider(): array
    {
        return [
//            'invalid key' => [['invalid' => 'invalid']],
//            'invalid value type' => [['support-email' => true]],
//            'invalid value length' => [
//                [
//                    'support-email' =>
//                        'invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalid' .
//                        'invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalid' .
//                        'invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinvalid@example.com'
//                ]
//            ],
//            'invalid email format' => [['support-email' => 'invalid']],
//            'invalid boolean format' => [['cold-wallet-is-active' => '23']],
//            'invalid amount (not integer)' => [['hotwallet-min-value' => '1234a']],
//            'invalid amount (negative)' => [['hotwallet-min-value' => '-1']],
//            'invalid amount (out of range)' => [['hotwallet-min-value' => '3875820600000000001']],
//            'invalid classifier setting' => [['site-classifier-local-banners' => 'invalid']],
//            'invalid registration mode' => [['registration-mode' => 'invalid']],
            'invalid date format mode' => [['panel-placeholder-update-time' => '2020-01-01 12:34']],
        ];
    }
}
