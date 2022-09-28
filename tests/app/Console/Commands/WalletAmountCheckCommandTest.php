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
use Adshares\Adserver\Mail\WalletFundsEmail;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Model\Currency;
use Adshares\Demand\Application\Dto\TransferMoneyResponse;
use Adshares\Demand\Application\Exception\TransferMoneyException;
use Adshares\Demand\Application\Service\TransferMoneyToColdWallet;
use Adshares\Demand\Application\Service\WalletFundsChecker;
use DateTime;
use Illuminate\Support\Facades\Mail;

class WalletAmountCheckCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:wallet:transfer:check';

    public function testColdWalletDisabled(): void
    {
        $this->artisan(self::SIGNATURE)
            ->expectsOutput('[Wallet] Cold wallet feature is disabled.')
            ->assertSuccessful();
    }

    public function testNoTransfer(): void
    {
        Config::updateAdminSettings([Config::COLD_WALLET_IS_ACTIVE => '1']);
        $this->app->bind(
            WalletFundsChecker::class,
            function () {
                $mock = self::createMock(WalletFundsChecker::class);
                $mock->method('calculateTransferValue')->willReturn(0);
                return $mock;
            }
        );

        $this->artisan(self::SIGNATURE)
            ->expectsOutput('[Wallet] No need to transfer clicks from Cold Wallet.')
            ->assertSuccessful();
    }

    public function testNoEmail(): void
    {
        Config::updateAdminSettings([Config::COLD_WALLET_IS_ACTIVE => '1']);
        $this->app->bind(
            WalletFundsChecker::class,
            function () {
                $mock = self::createMock(WalletFundsChecker::class);
                $mock->method('calculateTransferValue')->willReturn(1);
                return $mock;
            }
        );
        Config::upsertDateTime(Config::OPERATOR_WALLET_EMAIL_LAST_TIME, new DateTime());

        $this->artisan(self::SIGNATURE)
            ->expectsOutputToContain('[Wallet] Email does not need to be sent')
            ->assertSuccessful();
    }

    /**
     * @dataProvider appCurrencyProvider
     */
    public function testTransfer(Currency $currency, int $expectedWaitingPayments, $expectedUsersBalance): void
    {
        Config::updateAdminSettings([
            Config::COLD_WALLET_ADDRESS => '0001-00000024-FF89',
            Config::COLD_WALLET_IS_ACTIVE => '1',
            Config::CURRENCY => $currency->value,
        ]);
        $this->app->bind(
            WalletFundsChecker::class,
            function () use ($expectedWaitingPayments, $expectedUsersBalance) {
                $mock = self::createMock(WalletFundsChecker::class);
                $mock->method('calculateTransferValue')->willReturnCallback(
                    function (
                        $waitingPayments,
                        $allUsersBalance
                    ) use (
                        $expectedWaitingPayments,
                        $expectedUsersBalance
                    ) {
                        self::assertEquals($expectedWaitingPayments, $waitingPayments);
                        self::assertEquals($expectedUsersBalance, $allUsersBalance);
                        return 123450000000;
                    }
                );
                return $mock;
            }
        );

        UserLedgerEntry::factory()->create([
            'amount' => 2 * 10 ** 11,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_DEPOSIT,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => -1 * 10 ** 11,
            'status' => UserLedgerEntry::STATUS_BLOCKED,
            'type' => UserLedgerEntry::TYPE_AD_EXPENSE,
        ]);

        $this->artisan(self::SIGNATURE)
            ->expectsOutputToContain('[Wallet] Email has been sent to mail@example.com to transfer 1.2345 ADS')
            ->assertSuccessful();
        Mail::assertQueued(WalletFundsEmail::class);
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
                -100_000_000_000,// expenses in clicks
                100_000_000_000,// total in clicks
            ],
            'USD' => [
                Currency::USD,
                -300_030_003_001,// expenses in clicks, = x / 0.3333 + 1 (+1 due to rounding)
                300_030_003_000,// total in clicks, = x / 0.3333
            ],
        ];
    }
}
