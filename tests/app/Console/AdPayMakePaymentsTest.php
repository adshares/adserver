<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Console;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdPayMakePaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function testHandle(): void
    {
        /** @var Collection|EventLog[] $events */
        factory(EventLog::class)
            ->times(3)
            ->create(['pay_to' => AccountId::fromIncompleteString('0001-00000001')]);
        factory(EventLog::class)
            ->times(2)
            ->create(['pay_to' => AccountId::fromIncompleteString('0002-00000002')]);
        factory(EventLog::class)
            ->times(4)
            ->create(['pay_to' => AccountId::fromIncompleteString('0002-00000004')]);

        $this->artisan('ops:adpay:payments:make')
            ->expectsOutput('Found 9 payable events.')
            ->expectsOutput('In that, there are 3 recipients.')
            ->assertExitCode(0);

        $events = EventLog::all();
        self::assertCount(9, $events);

        $payments = Payment::all();
        self::assertCount(3, $payments);
    }
}
