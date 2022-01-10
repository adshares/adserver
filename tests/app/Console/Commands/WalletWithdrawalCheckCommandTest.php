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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Mail\WithdrawalSuccess;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class WalletWithdrawalCheckCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:wallet:withdrawal:check';

    public function testAutoWithdrawal(): void
    {
        Queue::fake();
        Mail::fake();
        $dummyExchangeRateRepository = new DummyExchangeRateRepository();
        $this->app->bind(ExchangeRateRepository::class, static function () use ($dummyExchangeRateRepository) {
            return $dummyExchangeRateRepository;
        });
        /** @var User $user1 */
        $user1 = factory(User::class)->create([
            'auto_withdrawal' => null,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);
        /** @var User $user2 */
        $user2 = factory(User::class)->create([
            'auto_withdrawal' => 150,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000002-BB2D')
        ]);
        /** @var User $user3 */
        $user3 = factory(User::class)->create([
            'auto_withdrawal' => 50,
            'wallet_address' => WalletAddress::fromString('ads:0001-00000003-AB0C')
        ]);
        /** @var User $user4 */
        $user4 = factory(User::class)->create([
            'auto_withdrawal' => 50,
            'wallet_address' => WalletAddress::fromString('bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a'),
            'email' => null,
        ]);
        factory(UserLedgerEntry::class)->create([
            'user_id' => $user1->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 1000
        ]);
        factory(UserLedgerEntry::class)->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 1000
        ]);
        factory(UserLedgerEntry::class)->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
            'amount' => 100
        ]);
        factory(UserLedgerEntry::class)->create([
            'user_id' => $user3->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 100
        ]);
        factory(UserLedgerEntry::class)->create([
            'user_id' => $user3->id,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
            'amount' => 100
        ]);
        factory(UserLedgerEntry::class)->create([
            'user_id' => $user4->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 500
        ]);
        $this->assertEquals(1000, $user1->getBalance());
        $this->assertEquals(1000, $user1->getWalletBalance());
        $this->assertEquals(1100, $user2->getBalance());
        $this->assertEquals(1000, $user2->getWalletBalance());
        $this->assertEquals(200, $user3->getBalance());
        $this->assertEquals(100, $user3->getWalletBalance());
        $this->assertEquals(500, $user4->getBalance());
        $this->assertEquals(500, $user4->getWalletBalance());
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        $this->assertEquals(1000, $user1->getBalance());
        $this->assertEquals(1000, $user1->getWalletBalance());
        $this->assertEquals(100, $user2->getBalance());
        $this->assertEquals(0, $user2->getWalletBalance());
        $this->assertEquals(200, $user3->getBalance());
        $this->assertEquals(100, $user3->getWalletBalance());
        $this->assertEquals(0, $user4->getBalance());
        $this->assertEquals(0, $user4->getWalletBalance());
        Queue::assertPushed(AdsSendOne::class, 2);
        Mail::assertQueued(WithdrawalSuccess::class, 1);
    }
}
