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
    }

    public function testBalancePushing(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::pushBlockedToProcessing();

        self::assertEquals(-195, UserLedgerEntry::getBalanceByUserId($user->id));

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-165, UserLedgerEntry::getBalanceByUserId($user->id));
    }

    public function testBalanceRemoval(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::removeProcessingExpenses();

        self::assertEquals(-180, UserLedgerEntry::getBalanceByUserId($user->id));
    }

    private function createAllEntries(User $user): void
    {
        $amountMap = [
            UserLedgerEntry::TYPE_UNKNOWN => 9,
            UserLedgerEntry::TYPE_DEPOSIT => 100,
            UserLedgerEntry::TYPE_WITHDRAWAL => -50,
            UserLedgerEntry::TYPE_AD_INCOME => 30,
            UserLedgerEntry::TYPE_AD_EXPENSE => -15,
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
