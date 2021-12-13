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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;

class UserTest extends TestCase
{
    public function testFetchByWalletAddress(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $user->wallet_address = new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E');
        $user->saveOrFail();

        $dbUser = User::fetchByWalletAddress(WalletAddress::fromString('ADS:0001-00000001-8B4E'));
        $this->assertNotNull($dbUser);
        $this->assertEquals($user->id, $dbUser->id);
    }

    public function testCreateAnonymous(): void
    {
        $address = new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E');
        $user = User::createAnonymous($address);

        $this->assertNotNull($user->uuid);
        $this->assertNull($user->email);
        $this->assertEquals($address, $user->wallet_address);
        $this->assertNotNull($user->auto_withdrawal);
        $this->assertEquals(100000000000, $user->auto_withdrawal);
    }
}
