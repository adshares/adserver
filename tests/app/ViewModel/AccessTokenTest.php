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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\ViewModel;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\AccessToken;
use DateTimeImmutable;
use Laravel\Passport\Bridge\Scope;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class AccessTokenTest extends TestCase
{
    public function testAccessToken(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        $client = self::createMock(ClientEntityInterface::class);
        $client->method('getIdentifier')->willReturn('test-client-id');
        $scopes = [new Scope('campaign.read')];

        $token = new AccessToken($user->id, $scopes, $client);
        $token->setExpiryDateTime((new DateTimeImmutable('+1 day')));
        $token->setPrivateKey($this->getCryptKey());
        $token->setIdentifier('test-id');

        $parser = new Parser(new JoseEncoder());
        $parsedToken = $parser->parse((string)$token);

        self::assertEquals('test-client-id', $parsedToken->claims()->get('aud')[0]);
        self::assertEquals('test-id', $parsedToken->claims()->get('jti'));
        self::assertEquals(['advertiser', 'publisher'], $parsedToken->claims()->get('roles'));
        self::assertEquals(['campaign.read'], $parsedToken->claims()->get('scopes'));
        self::assertEquals('test@example.com', $parsedToken->claims()->get('username'));
    }

    public function testAccessTokenWithoutUserIdentifier(): void
    {
        $client = self::createMock(ClientEntityInterface::class);
        $client->method('getIdentifier')->willReturn('test-client-id');
        $client->method('getName')->willReturn('test-client');
        $scopes = [new Scope('campaign.read')];

        $token = new AccessToken(null, $scopes, $client);
        $token->setExpiryDateTime((new DateTimeImmutable('+1 day')));
        $token->setPrivateKey($this->getCryptKey());
        $token->setIdentifier('test-id');

        $parser = new Parser(new JoseEncoder());
        $parsedToken = $parser->parse((string)$token);
        self::assertEquals('test-client-id', $parsedToken->claims()->get('aud')[0]);
        self::assertEquals('test-id', $parsedToken->claims()->get('jti'));
        self::assertEquals([], $parsedToken->claims()->get('roles'));
        self::assertEquals(['campaign.read'], $parsedToken->claims()->get('scopes'));
        self::assertEquals('test-client', $parsedToken->claims()->get('username'));
    }

    private function getCryptKey(): CryptKey
    {
        $privateKey = self::createMock(CryptKey::class);
        $privateKey->method('getKeyContents')
            ->willReturn(file_get_contents(base_path('tests/mock/Files/OAuth/oauth-private.key')));
        $privateKey->method('getPassPhrase')->willReturn('');
        return $privateKey;
    }
}
