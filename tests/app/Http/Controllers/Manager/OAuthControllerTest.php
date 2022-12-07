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

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

final class OAuthControllerTest extends TestCase
{
    public function testAuthorize(): void
    {
        $this->login();

        $response = $this->get(self::buildUri(false));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['location']);
        $location = $response->json('location');
        self::assertStringStartsWith(
            'https://example.com/callback',
            $location,
            sprintf('Invalid location: %s', $location)
        );
    }

    public function testAuthorizeWithRedirect(): void
    {
        $this->login();

        $response = $this->get(self::buildUri());

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringStartsWith(
            'https://example.com/callback',
            $location,
            sprintf('Invalid location header: %s', $location)
        );
    }

    public function testNoToken(): void
    {
        $response = $this->get(self::buildUri());

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertHeader('Location');
        $location = $response->headers->get('Location');
        self::assertStringStartsWith(
            'http://adpanel/auth/login',
            $location,
            sprintf('Invalid location header: %s', $location)
        );
    }

    public function testUserBanned(): void
    {
        $user = User::factory()->create(['is_banned' => true, 'ban_reason' => 'suspicious activity']);
        $this->login($user);

        $response = $this->get(self::buildUri());

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testInvalidClientId(): void
    {
        $this->login();

        $redirectUri = 'https://example.com/callback';
        $uri = sprintf(
            '/auth/authorize?client_id=%s&redirect_uri=%s&response_type=code&no_redirect=true',
            PHP_INT_MAX,
            $redirectUri
        );
        $response = $this->get($uri);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function buildUri(bool $redirect = true): string
    {
        $redirectUri = 'https://example.com/callback';
        $client = Client::factory()->create(['redirect' => $redirectUri]);
        return sprintf('/auth/authorize?client_id=%s&redirect_uri=%s&response_type=code', $client->id, $redirectUri)
            . ($redirect ? '' : '&no_redirect=true');
    }
}
