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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;

use function factory;

final class UserLedgerEntryTest extends TestCase
{
    public function testBalance(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        self::assertEquals(-185, $user->getBalance());
        self::assertEquals(-315, $user->getWalletBalance());
        self::assertEquals(130, $user->getBonusBalance());
    }

    public function testBalanceForAllUsers(): void
    {
        /** @var User $user */
        $user1 = factory(User::class)->create();
        $this->createAllEntries($user1);
        $user2 = factory(User::class)->create();
        $this->createAllEntries($user2);

        self::assertEquals(-370, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(-630, UserLedgerEntry::getWalletBalanceForAllUsers());
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
        UserLedgerEntry::blockAdExpense($user->id, 150);
    }

    public function testNegativeAmountBlockAdExpense(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createSomeEntries($user);

        $this->expectExceptionMessageRegExp('/Values need to be non-negative.*/');
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

        $this->expectExceptionMessageRegExp('/Values need to be non-negative.*/');
        UserLedgerEntry::processAdExpense($user->id, -10);
    }

    public function testBalancePushing(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::pushBlockedToProcessing();

        self::assertEquals(-185, $user->getBalance());
        self::assertEquals(-315, $user->getWalletBalance());
        self::assertEquals(130, $user->getBonusBalance());

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-135, $user->getBalance());
        self::assertEquals(-285, $user->getWalletBalance());
        self::assertEquals(150, $user->getBonusBalance());
    }

    public function testBalanceRemoval(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-160, $user->getBalance());
        self::assertEquals(-300, $user->getWalletBalance());
        self::assertEquals(140, $user->getBonusBalance());
    }

    public function testRefundAndBonus(): void
    {
        Config::updateAdminSettings([Config::REFERRAL_REFUND_ENABLED => 1]);
        Config::updateAdminSettings([Config::REFERRAL_REFUND_COMMISSION => 0.2]);

        /** @var User $user1 */
        $user1 = factory(User::class)->create();
        /** @var RefLink $refLink1 */
        $refLink1 = factory(RefLink::class)->create(['user_id' => $user1->id, 'refund' => 0.5, 'kept_refund' => 0.5]);
        /** @var User $user2 */
        $user2 = factory(User::class)->create(['ref_link_id' => $refLink1->id]);
        /** @var RefLink $refLink2 */
        $refLink2 = factory(RefLink::class)->create(['user_id' => $user2->id, 'kept_refund' => 0.7]);
        /** @var User $user3 */
        $user3 = factory(User::class)->create(['ref_link_id' => $refLink2->id]);
        $this->createSomeEntries($user3);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(0, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(240, $user3->getBalance());
        self::assertEquals(50, $user3->getWalletBalance());
        self::assertEquals(190, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user3->id, 240);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(7, $user2->getBalance());
        self::assertEquals(7, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(3, $user3->getBalance());
        self::assertEquals(0, $user3->getWalletBalance());
        self::assertEquals(3, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user2->id, 7);
        UserLedgerEntry::processAdExpense($user3->id, 3);

        self::assertEquals(1, $user1->getBalance());
        self::assertEquals(1, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(2, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(2, $user2->getBonusBalance());

        self::assertEquals(0, $user3->getBalance());
        self::assertEquals(0, $user3->getWalletBalance());
        self::assertEquals(0, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user1->id, 1);
        UserLedgerEntry::processAdExpense($user2->id, 2);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(0, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(0, $user3->getBalance());
        self::assertEquals(0, $user3->getWalletBalance());
        self::assertEquals(0, $user3->getBonusBalance());
    }

    public function testRefundAndBonusAfterDeadline(): void
    {
        Config::updateAdminSettings([Config::REFERRAL_REFUND_ENABLED => 1]);
        Config::updateAdminSettings([Config::REFERRAL_REFUND_COMMISSION => 0.2]);

        /** @var User $user1 */
        $user1 = factory(User::class)->create();
        /** @var RefLink $refLink1 */
        $refLink1 = factory(RefLink::class)->create(
            [
                'user_id' => $user1->id,
                'refund' => 0.5,
                'kept_refund' => 0.5,
                'refund_valid_until' => now()->subDay()
            ]
        );
        /** @var User $user2 */
        $user2 = factory(User::class)->create(['ref_link_id' => $refLink1->id]);
        $this->createSomeEntries($user2);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(240, $user2->getBalance());
        self::assertEquals(50, $user2->getWalletBalance());
        self::assertEquals(190, $user2->getBonusBalance());

        UserLedgerEntry::processAdExpense($user2->id, 240);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(0, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getBonusBalance());
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
            factory(UserLedgerEntry::class)->create(
                [
                    'status' => UserLedgerEntry::STATUS_ACCEPTED,
                    'type' => $entry[0],
                    'amount' => $entry[1],
                    'user_id' => $user->id,
                ]
            );
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
            UserLedgerEntry::TYPE_REFUND => 10,
        ];
        foreach (UserLedgerEntry::ALLOWED_TYPE_LIST as $type) {
            foreach (UserLedgerEntry::ALLOWED_STATUS_LIST as $status) {
                foreach ([true, false] as $delete) {
                    /** @var UserLedgerEntry $object */
                    $object = factory(UserLedgerEntry::class)->create(
                        [
                            'status' => $status,
                            'type' => $type,
                            'amount' => $amountMap[$type],
                            'user_id' => $user->id,
                        ]
                    );

                    if ($delete) {
                        $object->delete();
                    }
                }
            }
        }
    }
}
