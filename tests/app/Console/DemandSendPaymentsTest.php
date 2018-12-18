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

use Adshares\Ads\Entity\Tx;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\Ads;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DemandSendPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function testZero(): void
    {
        $this->artisan('ops:demand:payments:send')
            ->expectsOutput('Found 0 sendable payments.')
            ->assertExitCode(0);
    }

    public function testHandle(): void
    {
        /** @var Collection|Payment[] $payments */
        $payments = factory(Payment::class)
            ->times(9)
            ->create();

        $this->app->bind(
            Ads::class,
            function () {
                $ads = $this->createMock(Ads::class);
                $ads->method('sendPayments')->willReturn(new Tx());

                return $ads;
            }
        );

        $this->artisan('ops:demand:payments:send')
            ->expectsOutput('Found 9 sendable payments.')
            ->assertExitCode(0);

        $payments = Payment::all();
        self::assertCount(9, $payments);

        $payments->each(function (Payment $payment) {
            self::assertEquals(Payment::STATE_NEW, $payment->state);
        });

//        $payments = Payment::all();
//        self::assertCount(3, $payments);
//
//        $payments->each(function (Payment $payment) {
//            self::assertNotEmpty($payment->account_address);
//
//            $payment->events->each(function (EventLog $entry) use ($payment) {
//                self::assertEquals($entry->pay_to, $payment->account_address);
//            });
//        });
    }
}
