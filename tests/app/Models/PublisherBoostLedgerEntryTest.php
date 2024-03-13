<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\PublisherBoostLedgerEntry;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use DateTimeImmutable;

class PublisherBoostLedgerEntryTest extends TestCase
{
    public function testDeleteOutdated(): void
    {
        $oldDate = new DateTimeImmutable('-6 months');
        $outdatedEntry = PublisherBoostLedgerEntry::factory()->create([
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);
        $entry = PublisherBoostLedgerEntry::factory()->create();

        $freed = PublisherBoostLedgerEntry::deleteOutdated();

        self::assertSoftDeleted($outdatedEntry);
        self::assertNotSoftDeleted($entry);
        self::assertEquals(100_000_000_000, $freed);
    }

    public function testWithdraw(): void
    {
        $user = User::factory()->create();
        $entry0 = PublisherBoostLedgerEntry::factory()->create([
            'ads_address' => '0001-00000002-BB2D',
            'user_id' => $user,
        ]);
        [$entry1, $entry2, $entry3] = PublisherBoostLedgerEntry::factory()
            ->count(3)
            ->create(['user_id' => $user]);

        PublisherBoostLedgerEntry::withdraw($user->id, '0001-00000001-8B4E', 123_000_000_000);

        self::assertDatabaseHas(PublisherBoostLedgerEntry::class, [
            'id' => $entry0->id,
            'amount' => 100_000_000_000,
            'amount_left' => 100_000_000_000,
        ]);
        self::assertDatabaseHas(PublisherBoostLedgerEntry::class, [
            'id' => $entry1->id,
            'amount' => 100_000_000_000,
            'amount_left' => 0,
        ]);
        self::assertDatabaseHas(PublisherBoostLedgerEntry::class, [
            'id' => $entry2->id,
            'amount' => 100_000_000_000,
            'amount_left' => 77_000_000_000,
        ]);
        self::assertDatabaseHas(PublisherBoostLedgerEntry::class, [
            'id' => $entry3->id,
            'amount' => 100_000_000_000,
            'amount_left' => 100_000_000_000,
        ]);
    }
}
