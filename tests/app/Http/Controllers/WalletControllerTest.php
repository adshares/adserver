<?php
// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Infrastructure\Service\Sodium;
use Symfony\Component\HttpFoundation\Response;

class WalletControllerTest extends TestCase
{
    private const CONNECT_INIT_URI = '/api/wallet/connect/init';
    private const CONNECT_URI = '/api/wallet/connect';

    public function testConnectInit(): void
    {
        $this->login();
        $response = $this->get(self::CONNECT_INIT_URI);
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure([
            'message',
            'token',
            'gateways' => ['bsc']
        ]);
    }

    public function testAdsConnect(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        /** @var User $userDb */
        $userDb = User::fetchById($user->id);
        $this->assertNotNull($userDb->wallet_address);
        $this->assertEquals(WalletAddress::NETWORK_ADS, $userDb->wallet_address->getNetwork());
        $this->assertEquals('0001-00000001-8B4E', $userDb->wallet_address->getAddress());
    }

    public function testBscConnect(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //message:123abc
        $sign = '0xcfc6afca5c3e65b2f84dd8d3c5cd1272cf978794d338bdd5c025bc894e38aa024e0a287d96a67501417b6c10c25cb14f6c0e39927372a81bb6d50ccc83453e001b';
        $response = $this->post(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'bsc',
            'address' => '0xbbdfd8a8ce8b24ffdc9bc993b9d589d5442c019b',
            'sign' => $sign
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        /** @var User $userDb */
        $userDb = User::fetchById($user->id);
        $this->assertNotNull($userDb->wallet_address);
        $this->assertEquals(WalletAddress::NETWORK_BSC, $userDb->wallet_address->getNetwork());
        $this->assertEquals('0xbbdfd8a8ce8b24ffdc9bc993b9d589d5442c019b', $userDb->wallet_address->getAddress());
    }

    public function testInvalidConnectSign(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        $response = $this->post(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => '0x1231231231'
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testInvalidConnectToken(): void
    {
        $this->login();
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::CONNECT_URI, [
            'token' => 'foo_token',
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testNonExistedConnectToken(): void
    {
        $this->login();
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::CONNECT_URI, [
            'token' => '1231231231',
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testExpiredConnectToken(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ]);
        $token->valid_until = '2020-01-01 12:00:00';
        $token->saveOrFail();

        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::CONNECT_URI, [
            'token' => $token->uuid,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testInvalidConnectUser(): void
    {
        $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, factory(User::class)->create(), [
            'request' => [],
            'message' => $message,
        ]);

        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::CONNECT_URI, [
            'token' => $token->uuid,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
