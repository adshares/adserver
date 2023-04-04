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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use DateTimeImmutable;

final class UserLedgerEntryTest extends TestCase
{
    public function testBalance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createAllEntries($user);

        self::assertEquals(-95, $user->getBalance());
        self::assertEquals(-225, $user->getWalletBalance());
        self::assertEquals(-315, $user->getWithdrawableBalance());
        self::assertEquals(130, $user->getBonusBalance());
    }

    public function testBalanceForAllUsers(): void
    {
        /** @var User $user */
        $user1 = User::factory()->create();
        $this->createAllEntries($user1);
        $user2 = User::factory()->create();
        $this->createAllEntries($user2);

        self::assertEquals(-190, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(-450, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(-630, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(260, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testBalanceForDeletedUser(): void
    {
        /** @var User $user */
        $user1 = User::factory()->create(['deleted_at' => new DateTimeImmutable()]);
        $this->createAllEntries($user1);
        $user2 = User::factory()->create();
        $this->createAllEntries($user2);

        self::assertEquals(-95, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(-225, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(-315, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(130, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testBlockAdExpense(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createSomeEntries($user);

        self::assertEquals(510, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(320, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(190, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 10);

        self::assertEquals(500, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(320, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(180, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 190);

        self::assertEquals(310, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(310, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 270);

        self::assertEquals(40, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(40, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(40, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::blockAdExpense($user->id, 40);

        self::assertEquals(0, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testInvalidBlockAdExpense(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createAllEntries($user);

        $this->expectExceptionMessageMatches('/Insufficient funds for User.*/');
        UserLedgerEntry::blockAdExpense($user->id, 550);
    }

    public function testNegativeAmountBlockAdExpense(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createSomeEntries($user);

        $this->expectExceptionMessageMatches('/Values need to be non-negative.*/');
        UserLedgerEntry::blockAdExpense($user->id, -10);
    }

    public function testProcessAdExpense(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createSomeEntries($user);

        self::assertEquals(510, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(320, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(190, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 10);

        self::assertEquals(500, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(320, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(180, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 190);

        self::assertEquals(310, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(310, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(50, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 270);

        self::assertEquals(40, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(40, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(40, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());

        UserLedgerEntry::processAdExpense($user->id, 40);

        self::assertEquals(0, UserLedgerEntry::getBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getWalletBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getWithdrawableBalanceForAllUsers());
        self::assertEquals(0, UserLedgerEntry::getBonusBalanceForAllUsers());
    }

    public function testNegativeAmountProcessAdExpense(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createSomeEntries($user);

        $this->expectExceptionMessageMatches('/Values need to be non-negative.*/');
        UserLedgerEntry::processAdExpense($user->id, -10);
    }

    public function testBalancePushing(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createAllEntries($user);

        self::assertEquals(-95, $user->getBalance());
        self::assertEquals(-225, $user->getWalletBalance());
        self::assertEquals(-315, $user->getWithdrawableBalance());
        self::assertEquals(130, $user->getBonusBalance());

        UserLedgerEntry::pushBlockedToProcessing();

        self::assertEquals(-95, $user->getBalance());
        self::assertEquals(-225, $user->getWalletBalance());
        self::assertEquals(-315, $user->getWithdrawableBalance());
        self::assertEquals(130, $user->getBonusBalance());

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(15, $user->getBalance());
        self::assertEquals(-135, $user->getWalletBalance());
        self::assertEquals(-285, $user->getWithdrawableBalance());
        self::assertEquals(150, $user->getBonusBalance());
    }

    public function testBalanceRemoval(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createAllEntries($user);

        self::assertEquals(-95, $user->getBalance());
        self::assertEquals(-225, $user->getWalletBalance());
        self::assertEquals(-315, $user->getWithdrawableBalance());
        self::assertEquals(130, $user->getBonusBalance());

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-40, $user->getBalance());
        self::assertEquals(-180, $user->getWalletBalance());
        self::assertEquals(-300, $user->getWithdrawableBalance());
        self::assertEquals(140, $user->getBonusBalance());
    }

    public function testRefundAndBonus(): void
    {
        Config::updateAdminSettings([
            Config::REFERRAL_REFUND_COMMISSION => 0.2,
            Config::REFERRAL_REFUND_ENABLED => 1,
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();

        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var RefLink $refLink1 */
        $refLink1 = RefLink::factory()->create(['user_id' => $user1->id, 'refund' => 0.5, 'kept_refund' => 0.5]);
        /** @var User $user2 */
        $user2 = User::factory()->create(['ref_link_id' => $refLink1->id]);
        /** @var RefLink $refLink2 */
        $refLink2 = RefLink::factory()->create(['user_id' => $user2->id, 'kept_refund' => 0.7]);
        /** @var User $user3 */
        $user3 = User::factory()->create(['ref_link_id' => $refLink2->id]);
        $this->createSomeEntries($user3);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(0, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getWithdrawableBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(510, $user3->getBalance());
        self::assertEquals(320, $user3->getWalletBalance());
        self::assertEquals(50, $user3->getWithdrawableBalance());
        self::assertEquals(190, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user3->id, 240);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(7, $user2->getBalance());
        self::assertEquals(7, $user2->getWalletBalance());
        self::assertEquals(7, $user2->getWithdrawableBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(273, $user3->getBalance());
        self::assertEquals(270, $user3->getWalletBalance());
        self::assertEquals(50, $user3->getWithdrawableBalance());
        self::assertEquals(3, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user3->id, 273);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(45, $user2->getBalance());
        self::assertEquals(45, $user2->getWalletBalance());
        self::assertEquals(45, $user2->getWithdrawableBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(16, $user3->getBalance());
        self::assertEquals(0, $user3->getWalletBalance());
        self::assertEquals(0, $user3->getWithdrawableBalance());
        self::assertEquals(16, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user2->id, 45);
        UserLedgerEntry::processAdExpense($user3->id, 16);

        self::assertEquals(11, $user1->getBalance());
        self::assertEquals(11, $user1->getWalletBalance());
        self::assertEquals(11, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(11, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getWithdrawableBalance());
        self::assertEquals(11, $user2->getBonusBalance());

        self::assertEquals(0, $user3->getBalance());
        self::assertEquals(0, $user3->getWalletBalance());
        self::assertEquals(0, $user3->getWithdrawableBalance());
        self::assertEquals(0, $user3->getBonusBalance());

        UserLedgerEntry::processAdExpense($user1->id, 11);
        UserLedgerEntry::processAdExpense($user2->id, 11);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(0, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getWithdrawableBalance());
        self::assertEquals(0, $user2->getBonusBalance());

        self::assertEquals(0, $user3->getBalance());
        self::assertEquals(0, $user3->getWalletBalance());
        self::assertEquals(0, $user3->getWithdrawableBalance());
        self::assertEquals(0, $user3->getBonusBalance());
    }

    public function testRefundAndBonusAfterDeadline(): void
    {
        Config::updateAdminSettings([
            Config::REFERRAL_REFUND_COMMISSION => 0.2,
            Config::REFERRAL_REFUND_ENABLED => 1,
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();

        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var RefLink $refLink1 */
        $refLink1 = RefLink::factory()->create(
            [
                'user_id' => $user1->id,
                'refund' => 0.5,
                'kept_refund' => 0.5,
                'refund_valid_until' => now()->subDay()
            ]
        );
        /** @var User $user2 */
        $user2 = User::factory()->create(['ref_link_id' => $refLink1->id]);
        $this->createSomeEntries($user2);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(510, $user2->getBalance());
        self::assertEquals(320, $user2->getWalletBalance());
        self::assertEquals(50, $user2->getWithdrawableBalance());
        self::assertEquals(190, $user2->getBonusBalance());

        UserLedgerEntry::processAdExpense($user2->id, 510);

        self::assertEquals(0, $user1->getBalance());
        self::assertEquals(0, $user1->getWalletBalance());
        self::assertEquals(0, $user1->getWithdrawableBalance());
        self::assertEquals(0, $user1->getBonusBalance());

        self::assertEquals(0, $user2->getBalance());
        self::assertEquals(0, $user2->getWalletBalance());
        self::assertEquals(0, $user2->getWithdrawableBalance());
        self::assertEquals(0, $user2->getBonusBalance());
    }

    // Foreign ecosystem

    public function testGetFirstRecordByBatchId(): void
    {
        $batchId = UserLedgerEntry::getNewBatchId();
        $this->createBatchEntries($batchId);

        $ledgerEntry2 = UserLedgerEntry::getFirstRecordByBatchId($batchId);
        self::assertEquals(-100, $ledgerEntry2->amount);
        self::assertEquals(UserLedgerEntry::STATUS_PENDING, $ledgerEntry2->status);

        $batchId = 'and Invalid Batch Id';

        $ledgerEntry2 = UserLedgerEntry::getFirstRecordByBatchId($batchId);
        self::assertNull($ledgerEntry2);
    }

    public function testFailAllRecordsInBatch(): void
    {
        $batchId = UserLedgerEntry::getNewBatchId();
        $this->createBatchEntries($batchId);
        UserLedgerEntry::failAllRecordsInBatch($batchId, UserLedgerEntry::STATUS_NET_ERROR);
        $ledgerEntry2 = UserLedgerEntry::getFirstRecordByBatchId($batchId);
        self::assertEquals(UserLedgerEntry::STATUS_NET_ERROR, $ledgerEntry2->status);
    }

    public function testAcceptAllRecordsInBatch(): void
    {
        $batchId = UserLedgerEntry::getNewBatchId();
        $this->createBatchEntries($batchId);
        $txid = '1234';
        UserLedgerEntry::acceptAllRecordsInBatch($batchId, $txid);
        $ledgerEntry2 = UserLedgerEntry::getFirstRecordByBatchId($batchId);
        self::assertEquals(UserLedgerEntry::STATUS_ACCEPTED, $ledgerEntry2->status);
        self::assertEquals($txid, $ledgerEntry2->txid);
    }

    public function testBalancesByBatchId(): void
    {
        $batchId = UserLedgerEntry::getNewBatchId();
        $this->createBatchEntries($batchId);
        $txid = '1234';
        UserLedgerEntry::acceptAllRecordsInBatch($batchId, $txid);
        $balances = UserLedgerEntry::balancesByBatchId($batchId);

        self::assertCount(4, $balances);
        self::assertEquals('0x0002D752001721d43d8F04AC4FDfb7aE2784E001', $balances[0]['uid']);
        self::assertEquals(100, $balances[0]['ads']);
    }

    public function testAllWalletBalanceIfAny(): void
    {
        $users = array(
            User::factory()->create(),
            User::factory()->create(),
            User::factory()->create(),
            User::factory()->create());
        $users[0]->foreign_wallet_address = '0x0001D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[0]->saveOrFail();
        $this->createSomeEntries($users[0]);

        // User[1] has no any entires. sum is zero
        $users[1]->foreign_wallet_address = '0x0002D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[1]->saveOrFail();

        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_AD_INCOME,
                'amount' => 10,
                'user_id' => $users[1]->id,
            ]
        );

        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                'amount' => -10,
                'user_id' => $users[1]->id,
            ]
        );

        // User[2]
        $users[2]->foreign_wallet_address = '0x0003D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[2]->saveOrFail();
        $this->createSomeEntries($users[2]);
        // User3 has entires, but its not a foreign user
        $this->createSomeEntries($users[3]);

        $total = UserLedgerEntry::getWalletBalanceForForeignUsers();
        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();
        self::assertTrue($total < UserLedgerEntry::getWalletBalanceForAllUsers());
        // user[3] is not a foreign user. its balance should affect getWalletBalanceForAllUsers, but not getWalletBalanceForForeignUsers

        self::assertCount(2, $balances);
        foreach ($balances as $entry) {
            if($entry['wallet'] === $users[0]->foreign_wallet_address) {
                self::assertEquals(50, $entry['share']);
            }
            if($entry['wallet'] === $users[1]->foreign_wallet_address) {
                self::assertEquals(false, true);
            }
        }
    }

    public function testSuccessThenIfAny(): void
    {
        $batchId = UserLedgerEntry::getNewBatchId();
        $users = array(User::factory()->create(),User::factory()->create(),User::factory()->create());
        $users[0]->foreign_wallet_address = '0x0001D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[0]->saveOrFail();
        $this->createAllEntries($users[0]);
        $users[1]->foreign_wallet_address = '0x0002D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[1]->saveOrFail();
        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_AD_INCOME,
                'amount' => 10,
                'user_id' => $users[1]->id,
            ]
        );

        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                'amount' => -10,
                'user_id' => $users[1]->id,
            ]
        );
        // User2 has no any entires
        $users[2]->foreign_wallet_address = '0x0003D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[2]->saveOrFail();
        $this->createAllEntries($users[2]);

        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();

        foreach ($balances as $entry) {
            $user = null;
            foreach ($users as $uItem) {
                if($uItem->foreign_wallet_address === $entry['wallet']){
                    $user = $uItem;
                }
            }
            UserLedgerEntry::constructForeignEntry(
                $batchId,
                $user->id,
                -$entry['share']
            )->save();
        }
        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();
        self::assertCount(0, $balances);
        $txid = '1234';
        UserLedgerEntry::acceptAllRecordsInBatch($batchId, $txid);
        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();
        self::assertCount(0, $balances);
    }

    public function testFailThenIfAny(): void
    {
        $batchId = UserLedgerEntry::getNewBatchId();
        $users = array(User::factory()->create(),User::factory()->create(),User::factory()->create());
        $users[0]->foreign_wallet_address = '0x0001D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[0]->saveOrFail();
        $this->createAllEntries($users[0]);
        $users[1]->foreign_wallet_address = '0x0002D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[1]->saveOrFail();
        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_AD_INCOME,
                'amount' => 10,
                'user_id' => $users[1]->id,
            ]
        );

        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                'amount' => -10,
                'user_id' => $users[1]->id,
            ]
        );
        // User2 has no any entires
        $users[2]->foreign_wallet_address = '0x0003D752001721d43d8F04AC4FDfb7aE2784E8AF';
        $users[2]->saveOrFail();
        $this->createAllEntries($users[2]);

        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();

        foreach ($balances as $entry) {
            $user = null;
            foreach ($users as $uItem) {
                if($uItem->foreign_wallet_address === $entry['wallet']){
                    $user = $uItem;
                }
            }
            UserLedgerEntry::constructForeignEntry(
                $batchId,
                $user->id,
                -$entry['share']
            )->save();
        }
        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();
        self::assertCount(0, $balances);
        UserLedgerEntry::failAllRecordsInBatch($batchId, UserLedgerEntry::STATUS_NET_ERROR);
        $balances = UserLedgerEntry::allForeignWalletBalanceIfAny();
        // If a transaction is failed, it should be checked manually. 
        self::assertCount(0, $balances);
    }

    private function createBatchEntries(string $batchId): void
    {
        $entries = [
            100,
            40,
            1,
            11,
        ];
        $row = 0;
        foreach ($entries as $entry) {
            $row = $row + 1;
            $user = User::factory()->create();
            $user->foreign_wallet_address = sprintf('0x0002D752001721d43d8F04AC4FDfb7aE2784E%03d', $row);
            $user->save();
            UserLedgerEntry::constructForeignEntry(
                $batchId,
                $user->id,
                -$entry
            )->save();

        }
    }

    // End of tests for foreign ecosystem


    private function createSomeEntries(User $user): void
    {
        $entries = [
            [UserLedgerEntry::TYPE_DEPOSIT, 100],
            [UserLedgerEntry::TYPE_WITHDRAWAL, -50],
            [UserLedgerEntry::TYPE_BONUS_INCOME, 200],
            [UserLedgerEntry::TYPE_BONUS_EXPENSE, -10],
            [UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT, 300],
            [UserLedgerEntry::TYPE_NON_WITHDRAWABLE_EXPENSE, -30],
        ];

        foreach ($entries as $entry) {
            UserLedgerEntry::factory()->create(
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
            UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT => 300,
            UserLedgerEntry::TYPE_NON_WITHDRAWABLE_EXPENSE => -30,
        ];
        foreach (UserLedgerEntry::ALLOWED_TYPE_LIST as $type) {
            foreach (UserLedgerEntry::ALLOWED_STATUS_LIST as $status) {
                foreach ([true, false] as $delete) {
                    /** @var UserLedgerEntry $object */
                    $object = UserLedgerEntry::factory()->create(
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
