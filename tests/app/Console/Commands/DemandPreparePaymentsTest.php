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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Database\Eloquent\Collection;

class DemandPreparePaymentsTest extends ConsoleTestCase
{
    public function testZero(): void
    {
        $this->artisan('ops:demand:payments:prepare')
            ->expectsOutput('Found 0 payable events.')
            ->assertExitCode(0);
    }

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

        $this->artisan('ops:demand:payments:prepare')
            ->expectsOutput('Found 9 payable events.')
            ->expectsOutput('In that, there are 3 recipients,')
            ->assertExitCode(0);

        $events = EventLog::all();
        self::assertCount(9, $events);

        $events->each(function (EventLog $entry) {
            self::assertNotEmpty($entry->payment_id);
        });

        $payments = Payment::all();
        self::assertCount(4, $payments);

        $payments->each(function (Payment $payment) {
            self::assertNotEmpty($payment->account_address);
            self::assertEquals(Payment::STATE_NEW, $payment->state);

            $payment->events->each(function (EventLog $entry) use ($payment) {
                self::assertEquals($entry->pay_to, $payment->account_address);
            });
        });
    }
}
