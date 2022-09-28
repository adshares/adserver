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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Model\Currency;
use Adshares\Demand\Application\Dto\TransferMoneyResponse;
use Adshares\Demand\Application\Exception\TransferMoneyException;
use Adshares\Demand\Application\Service\TransferMoneyToColdWallet;

class TransferMoneyToColdWalletCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:wallet:transfer:cold';

    public function testColdWalletDisabled(): void
    {
        $this->artisan(self::SIGNATURE)
            ->expectsOutput('[Wallet] Cold wallet feature is disabled.')
            ->assertSuccessful();
    }

    public function testInsufficientFunds(): void
    {
        Config::updateAdminSettings([Config::COLD_WALLET_IS_ACTIVE => '1']);
        $this->app->bind(
            TransferMoneyToColdWallet::class,
            function () {
                $mock = self::createMock(TransferMoneyToColdWallet::class);
                $mock->method('transfer')->willThrowException(new TransferMoneyException('Test Exception'));
                return $mock;
            }
        );

        $this->artisan(self::SIGNATURE)
            ->expectsOutput('Test Exception')
            ->assertSuccessful();
    }

    public function testNoTransfer(): void
    {
        Config::updateAdminSettings([Config::COLD_WALLET_IS_ACTIVE => '1']);
        $this->app->bind(
            TransferMoneyToColdWallet::class,
            function () {
                $mock = self::createMock(TransferMoneyToColdWallet::class);
                $mock->method('transfer')->willReturn(null);
                return $mock;
            }
        );

        $this->artisan(self::SIGNATURE)
            ->expectsOutput('[Wallet] No clicks amount to transfer between Hot and Cold wallets.')
            ->assertSuccessful();
    }

    /**
     * @dataProvider appCurrencyProvider
     */
    public function testTransfer(Currency $currency, int $expectedAmount): void
    {
        Config::updateAdminSettings([
            Config::COLD_WALLET_ADDRESS => '0001-00000024-FF89',
            Config::COLD_WALLET_IS_ACTIVE => '1',
            Config::CURRENCY => $currency->value,
        ]);
        $this->app->bind(
            TransferMoneyToColdWallet::class,
            function () use ($expectedAmount) {
                $mock = self::createMock(TransferMoneyToColdWallet::class);
                $mock->method('transfer')->willReturnCallback(function ($amount) use ($expectedAmount) {
                    self::assertEquals($expectedAmount, $amount);
                    return new TransferMoneyResponse(123456000000, '0001:00000001:0001');
                });
                return $mock;
            }
        );

        UserLedgerEntry::factory()->create([
            'status' => UserLedgerEntry::STATUS_BLOCKED,
            'amount' => -1 * 10 ** 11,
        ]);

        $this->artisan(self::SIGNATURE)
            ->expectsOutputToContain('[Wallet] Successfully transfer 123456000000 clicks')
            ->assertSuccessful();
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertSuccessful();
    }

    public function appCurrencyProvider(): array
    {
        return [
            'ADS' => [
                Currency::ADS,
                -100_000_000_000,// amount in clicks
            ],
            'USD' => [
                Currency::USD,
                -300_030_003_001,// amount in clicks, = x / 0.3333 + 1 (+1 due to rounding)
            ],
        ];
    }
}
