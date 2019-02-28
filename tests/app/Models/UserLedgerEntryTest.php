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
use function in_array;

final class UserLedgerEntryTest extends TestCase
{
    use RefreshDatabase;

    public function testBalance(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        self::assertEquals(-50, UserLedgerEntry::getBalanceByUserId($user->id));
    }

    public function testBalancePushing(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        self::assertEquals(-50, UserLedgerEntry::getBalanceByUserId($user->id));

        UserLedgerEntry::pushBlockedToProcessing();

        self::assertEquals(-50, UserLedgerEntry::getBalanceByUserId($user->id));

        UserLedgerEntry::removeProcessingExpenditures();

        self::assertEquals(0, UserLedgerEntry::getBalanceByUserId($user->id));
    }

    public function testBalanceRemoval(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->createAllEntries($user);

        UserLedgerEntry::removeProcessingExpenditures();

        self::assertEquals(-25, UserLedgerEntry::getBalanceByUserId($user->id));
    }

    private function createAllEntries(User $user): void
    {
        foreach (UserLedgerEntry::ALLOWED_TYPE_LIST as $type) {
            $debit = in_array($type, UserLedgerEntry::DEBIT_TYPES, true);

            foreach (UserLedgerEntry::ALLOWED_STATUS_LIST as $status) {
                foreach ([true, false] as $delete) {
                    /** @var UserLedgerEntry $object */
                    $object = factory(UserLedgerEntry::class)->create([
                        'status' => $status,
                        'type' => $type,
                        'amount' => $debit ? -25 : 100,
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
