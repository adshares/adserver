<?php declare(strict_types = 1);
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

use Adshares\Adserver\Models\Tests\Data;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function factory;

final class UserLedgerEntryTest extends TestCase
{
    use RefreshDatabase;

    public function testBalance(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        self::assertEquals(-195, UserLedgerEntry::getBalanceByUserId($user->id));
        self::assertEquals(-325, UserLedgerEntry::getWalletBalanceByUserId($user->id));
        self::assertEquals(130, UserLedgerEntry::getBonusBalanceByUserId($user->id));
    }

    public function testBalanceForAllUsers(): void
    {
        /** @var User $user */
        $user1 = factory(User::class)->create();
        $this->createAllEntries($user1);
        $user2 = factory(User::class)->create();
        $this->createAllEntries($user2);

        self::assertEquals(-390, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(-650, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(260, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testBlockAdExpense(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createSomeEntries($user);

        self::assertEquals(240, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(190, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 10);

        self::assertEquals(230, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(180, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 190);

        self::assertEquals(40, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(40, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 20);

        self::assertEquals(20, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(20, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testInvalidBlockAdExpense(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        $this->expectExceptionMessageRegExp('/Insufficient funds for User.*/');
        UserLedgerEntry::blockAdExpense($user->id, 10);
    }

    public function testNegativeAmountBlockAdExpense(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createSomeEntries($user);

        $this->expectExceptionMessageRegExp('/Amount needs to be non-negative.*/');
        UserLedgerEntry::blockAdExpense($user->id, -10);
    }

    public function testProcessAdExpense(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createSomeEntries($user);

        self::assertEquals(240, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(190, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 10);

        self::assertEquals(230, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(180, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 190);

        self::assertEquals(40, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(40, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 20);

        self::assertEquals(20, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(20, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testNegativeAmountProcessAdExpense(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createSomeEntries($user);

        $this->expectExceptionMessageRegExp('/Amount needs to be non-negative.*/');
        UserLedgerEntry::processAdExpense($user->id, -10);
    }

    public function testBalancePushing(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::pushBlockedToProcessing();

        self::assertEquals(-195, UserLedgerEntry::getBalanceByUserId($user->id));
        self::assertEquals(-325, UserLedgerEntry::getWalletBalanceByUserId($user->id));
        self::assertEquals(130, UserLedgerEntry::getBonusBalanceByUserId($user->id));

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-145, UserLedgerEntry::getBalanceByUserId($user->id));
        self::assertEquals(-295, UserLedgerEntry::getWalletBalanceByUserId($user->id));
        self::assertEquals(150, UserLedgerEntry::getBonusBalanceByUserId($user->id));
    }

    public function testBalanceRemoval(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-170, UserLedgerEntry::getBalanceByUserId($user->id));
        self::assertEquals(-310, UserLedgerEntry::getWalletBalanceByUserId($user->id));
        self::assertEquals(140, UserLedgerEntry::getBonusBalanceByUserId($user->id));
    }

    private function createSomeEntries(User $user): void
    {
        $entries = [
            [UserLedgerEntry::TYPE_DEPOSIT, 100],
            [UserLedgerEntry::TYPE_WITHDRAWAL, -50],
            [UserLedgerEntry::TYPE_BONUS_INCOME, 200],
            [UserLedgerEntry::TYPE_BONUS_EXPENSE, -10],
        ];

        foreach ($entries as $entry) {
            $object = factory(UserLedgerEntry::class)->create([
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => $entry[0],
                'amount' => $entry[1],
                'user_id' => $user->id,
            ]);
        }
    }

    private function createAllEntries(User $user): void
    {
        $amountMap = [
            UserLedgerEntry::TYPE_UNKNOWN => 9,
            UserLedgerEntry::TYPE_DEPOSIT => 100,
            UserLedgerEntry::TYPE_WITHDRAWAL => -50,
            UserLedgerEntry::TYPE_AD_INCOME => 30,
            UserLedgerEntry::TYPE_AD_EXPENSE => -15,
            UserLedgerEntry::TYPE_BONUS_INCOME => 200,
            UserLedgerEntry::TYPE_BONUS_EXPENSE => -10,
        ];
        foreach (UserLedgerEntry::ALLOWED_TYPE_LIST as $type) {
            foreach (UserLedgerEntry::ALLOWED_STATUS_LIST as $status) {
                foreach ([true, false] as $delete) {
                    /** @var UserLedgerEntry $object */
                    $object = factory(UserLedgerEntry::class)->create([
                        'status' => $status,
                        'type' => $type,
                        'amount' => $amountMap[$type],
                        'user_id' => $user->id,
                    ]);

                    if ($delete) {
                        $object->delete();
                    }
                }
            }
        }
    }
}
