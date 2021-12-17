<?php

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

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = $response->json();

        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('gateways', $data);
        $this->assertArrayHasKey('bsc', $data['gateways']);
        $this->assertNotEmpty($data['gateways']['bsc']);
    }

    public function testAdsConnect(): void
    {
        $userId = $this->login()->id;
        $init = $this->get(self::CONNECT_INIT_URI)->json();

        $sign = Sodium::sign(
            'CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB',
            'message:' . $init['message']
        );
        $response = $this->post(self::CONNECT_INIT_URI, [
            'token' => $init['token'],
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        /** @var User $user */
        $user = User::get($userId);
        $this->assertNotNull($user->wallet_address);
        $this->assertEquals(WalletAddress::NETWORK_ADS, $user->wallet_address->getNetwork());
        $this->assertEquals('0001-00000001-8B4E', $user->wallet_address->getAddress());
    }

    public function testBscConnect(): void
    {
        $userId = $this->login()->id;
        $init = $this->get(self::CONNECT_INIT_URI)->json();

        $sign = Sodium::sign('CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB', $init['message']);
        $response = $this->post(self::CONNECT_INIT_URI, [
            'token' => $init['token'],
            'network' => 'bsc',
            'address' => '0001-00000001-8B4E',
            'sign' => $sign
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        /** @var User $user */
        $user = User::get($userId);
        $this->assertNotNull($user->wallet_address);
        $this->assertEquals(WalletAddress::NETWORK_BSC, $user->wallet_address->getNetwork());
        $this->assertEquals('0001-00000001-8B4E', $user->wallet_address->getAddress());
    }
}
