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

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Symfony\Component\HttpFoundation\Response;

class SettingsControllerTest extends TestCase
{
    private const AUTO_WITHDRAW_URI = '/api/wallet/auto-withdrawal';

    public function testEnableAutoWithdrawal(): void
    {
        $user = $this->login();
        $user->wallet_address = new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E');
        $user->saveOrFail();

        $this->assertFalse($user->is_auto_withdrawal);
        $this->assertEquals(0, $user->auto_withdrawal_limit);

        $response = $this->patch(self::AUTO_WITHDRAW_URI, [
            'auto_withdrawal' => 100_000_000_000_00,
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = $response->json('adserverWallet');
        $this->assertTrue($data['isAutoWithdrawal']);
        $this->assertEquals(100_000_000_000_00, $data['autoWithdrawalLimit']);

        /** @var User $userDb */
        $userDb = User::fetchById($user->id);
        $this->assertTrue($userDb->is_auto_withdrawal);
        $this->assertEquals(100_000_000_000_00, $userDb->auto_withdrawal_limit);
    }

    public function testDisableAutoWithdrawal(): void
    {
        $user = $this->login();
        $user->wallet_address = new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E');
        $user->auto_withdrawal = 100_000_000_000_00;
        $user->saveOrFail();

        $this->assertTrue($user->is_auto_withdrawal);
        $this->assertEquals(100_000_000_000_00, $user->auto_withdrawal_limit);

        $response = $this->patch(self::AUTO_WITHDRAW_URI, [
            'auto_withdrawal' => null,
        ]);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = $response->json('adserverWallet');
        $this->assertFalse($data['isAutoWithdrawal']);
        $this->assertEquals(0, $data['autoWithdrawalLimit']);

        /** @var User $userDb */
        $userDb = User::fetchById($user->id);
        $this->assertFalse($userDb->is_auto_withdrawal);
        $this->assertEquals(0, $userDb->auto_withdrawal_limit);
    }

    public function testInvalidAmountAutoWithdrawal(): void
    {
        $user = $this->login();
        $user->wallet_address = new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E');
        $user->saveOrFail();

        $response = $this->patch(self::AUTO_WITHDRAW_URI, [
            'auto_withdrawal' => 'foo',
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testTooLowAmountAutoWithdrawal(): void
    {
        $user = $this->login();
        $user->wallet_address = new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E');
        $user->saveOrFail();

        $response = $this->patch(self::AUTO_WITHDRAW_URI, [
            'auto_withdrawal' => 100,
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testNoWalletAddressAutoWithdrawal(): void
    {
        $user = $this->login();
        $user->wallet_address = null;
        $user->saveOrFail();

        $response = $this->patch(self::AUTO_WITHDRAW_URI, [
            'auto_withdrawal' => 100_000_000_000_00,
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testUnsupportedWalletNetworkAutoWithdrawal(): void
    {
        $user = $this->login();
        $user->wallet_address = new WalletAddress(
            WalletAddress::NETWORK_ETH,
            '0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a'
        );
        $user->saveOrFail();

        $response = $this->patch(self::AUTO_WITHDRAW_URI, [
            'auto_withdrawal' => 1_000_000_000_000_00,
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
