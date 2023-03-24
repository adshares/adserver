<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Mail\WithdrawalSuccess;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class WalletWithdrawalCheckCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:wallet:withdrawal:check';

    public function testAutoWithdrawal(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create([
            'auto_withdrawal' => null,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);
        /** @var User $user2 */
        $user2 = User::factory()->create([
            'auto_withdrawal' => 150,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000002-BB2D')
        ]);
        /** @var User $user3 */
        $user3 = User::factory()->create([
            'auto_withdrawal' => 50,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000003-AB0C')
        ]);
        /** @var User $user4 */
        $user4 = User::factory()->create([
            'auto_withdrawal' => 50,
            'wallet_address' => WalletAddress::fromString('bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a'),
            'email' => null,
        ]);

        UserLedgerEntry::factory()->create([
            'user_id' => $user1->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 1000
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 600
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT,
            'amount' => 400
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
            'amount' => 100
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user3->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 100
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user3->id,
            'type' => UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT,
            'amount' => 100
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user3->id,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
            'amount' => 100
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user4->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 500
        ]);

        $this->assertEquals(1000, $user1->getBalance());
        $this->assertEquals(1000, $user1->getWalletBalance());
        $this->assertEquals(1000, $user1->getWithdrawableBalance());
        $this->assertEquals(0, $user1->getBonusBalance());

        $this->assertEquals(1100, $user2->getBalance());
        $this->assertEquals(1000, $user2->getWalletBalance());
        $this->assertEquals(600, $user2->getWithdrawableBalance());
        $this->assertEquals(100, $user2->getBonusBalance());

        $this->assertEquals(300, $user3->getBalance());
        $this->assertEquals(200, $user3->getWalletBalance());
        $this->assertEquals(100, $user3->getWithdrawableBalance());
        $this->assertEquals(100, $user3->getBonusBalance());

        $this->assertEquals(500, $user4->getBalance());
        $this->assertEquals(500, $user4->getWalletBalance());
        $this->assertEquals(500, $user4->getWithdrawableBalance());
        $this->assertEquals(0, $user4->getBonusBalance());

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $this->assertEquals(1000, $user1->getBalance());
        $this->assertEquals(1000, $user1->getWalletBalance());
        $this->assertEquals(1000, $user1->getWithdrawableBalance());
        $this->assertEquals(0, $user1->getBonusBalance());

        $this->assertEquals(500, $user2->getBalance());
        $this->assertEquals(400, $user2->getWalletBalance());
        $this->assertEquals(0, $user2->getWithdrawableBalance());
        $this->assertEquals(100, $user2->getBonusBalance());

        $this->assertEquals(300, $user3->getBalance());
        $this->assertEquals(200, $user3->getWalletBalance());
        $this->assertEquals(100, $user3->getWithdrawableBalance());
        $this->assertEquals(100, $user3->getBonusBalance());

        $this->assertEquals(0, $user4->getBalance());
        $this->assertEquals(0, $user4->getWalletBalance());
        $this->assertEquals(0, $user4->getWithdrawableBalance());
        $this->assertEquals(0, $user4->getBonusBalance());

        Queue::assertPushed(AdsSendOne::class, 2);
        Mail::assertQueued(WithdrawalSuccess::class, 1);
    }

    /**
     * @dataProvider appCurrencyProvider
     */
    public function testAutoWithdrawalByAppCurrency(
        Currency $currency,
        int $amount,
        int $expectedBaseAmount,
        int $expectedAmountInClicks
    ): void {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);
        /** @var User $user */
        $user = User::factory()->create([
            'auto_withdrawal' => 0,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000002-BB2D'),
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => $amount,
        ]);

        $this->assertEquals($amount, $user->getBalance());
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        $this->assertEquals(0, $user->getBalance());
        Queue::assertPushed(function (AdsSendOne $job) use ($expectedAmountInClicks) {
            return $expectedAmountInClicks === $job->getAmount();
        });
        Mail::assertQueued(function (WithdrawalSuccess $mail) use ($expectedBaseAmount) {
            return $expectedBaseAmount === $mail->getAmount();
        });
    }

    public function testAutoWithdrawalNoWalletAddress(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'auto_withdrawal' => 0,
            'wallet_address' => null,
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 10_005_000_000_000,
        ]);

        $this->assertEquals(10_005_000_000_000, $user->getBalance());
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        $this->assertEquals(10_005_000_000_000, $user->getBalance());
        Queue::assertPushed(AdsSendOne::class, 0);
        Mail::assertQueued(WithdrawalSuccess::class, 0);
    }

    public function testAutoWithdrawalUnsupportedNetwork(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'auto_withdrawal' => 0,
            'wallet_address' => WalletAddress::fromString('eth:0x0123456789012345678901234567890123456789'),
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 10_005_000_000_000,
        ]);

        $this->assertEquals(10_005_000_000_000, $user->getBalance());
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        $this->assertEquals(10_005_000_000_000, $user->getBalance());
        Queue::assertPushed(AdsSendOne::class, 0);
        Mail::assertQueued(WithdrawalSuccess::class, 0);
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
                10_005_000_000_000,// withdraw amount (+fee) in currency
                10_000_000_000_000,// withdraw amount (no fee) in currency
                10_000_000_000_000,// sent amount in clicks
            ],
            'USD' => [
                Currency::USD,
                10_005_000_000_000,// withdraw amount (+fee) in currency
                10_000_000_000_000,// withdraw amount (no fee) in currency
                30_003_000_300_030,// sent amount in clicks, = x / 0.3333
            ],
        ];
    }
}
