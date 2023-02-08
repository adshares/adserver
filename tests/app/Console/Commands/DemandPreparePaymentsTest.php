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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Commands\DemandPreparePayments;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Services\Demand\AdPayPaymentReportProcessor;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Infrastructure\Service\CommunityFeeReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Illuminate\Database\Eloquent\Collection;

class DemandPreparePaymentsTest extends ConsoleTestCase
{
    public function testZero(): void
    {
        $this->artisan(DemandPreparePayments::COMMAND_SIGNATURE)
            ->expectsOutput('Found 0 payable events.')
            ->assertExitCode(0);
    }

    public function testLock(): void
    {
        $lockerMock = self::createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(DemandPreparePayments::COMMAND_SIGNATURE)
            ->expectsOutput('Command ops:demand:payments:prepare already running')
            ->assertExitCode(1);
    }

    public function testHandle(): void
    {
        /** @var Collection|EventLog[] $events */
        EventLog::factory()
            ->times(3)
            ->create(['pay_to' => AccountId::fromIncompleteString('0001-00000001')]);
        EventLog::factory()
            ->times(2)
            ->create(['pay_to' => AccountId::fromIncompleteString('0002-00000002')]);
        EventLog::factory()
            ->times(4)
            ->create(['pay_to' => AccountId::fromIncompleteString('0002-00000004')]);

        $this->artisan(DemandPreparePayments::COMMAND_SIGNATURE)
            ->expectsOutput('Found 9 payable events.')
            ->expectsOutput('In that, there are 3 recipients')
            ->assertExitCode(0);

        $events = EventLog::all();
        self::assertCount(9, $events);

        $events->each(function (EventLog $entry) {
            self::assertNotEmpty($entry->payment_id);
        });

        $payments = Payment::all();
        self::assertCount(5, $payments);// 3 * adserver + license + community

        $payments->each(function (Payment $payment) {
            self::assertNotEmpty($payment->account_address);
            self::assertEquals(Payment::STATE_NEW, $payment->state);

            $payment->events->each(function (EventLog $entry) use ($payment) {
                self::assertEquals($entry->pay_to, $payment->account_address);
            });
        });
    }

    public function testHandleEventFees(): void
    {
        Config::updateAdminSettings([Config::OPERATOR_TX_FEE => 0.5]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $communityFeeReader = self::createMock(CommunityFeeReader::class);
        $communityFeeReader->method('getAddress')->willReturn(new AccountId('0001-00000024-FF89'));
        $communityFeeReader->method('getFee')->willReturn(0.5);
        $this->instance(CommunityFeeReader::class, $communityFeeReader);
        $licenseReader = self::createMock(LicenseReader::class);
        $licenseReader->method('getAddress')->willReturn(new AccountId('0001-00000002-BB2D'));
        $licenseReader->method('getFee')->willReturn(0.5);
        $this->instance(LicenseReader::class, $licenseReader);
        /** @var EventLog $event */
        $event = EventLog::factory()->create([
            'event_value_currency' => 1000,
            'exchange_rate' => 1,
            'event_value' => 1000,
            'payment_status' => AdPayPaymentReportProcessor::STATUS_PAYMENT_ACCEPTED,
            'pay_to' => new AccountId('0001-00000001-8B4E'),
        ]);

        $this->artisan(DemandPreparePayments::COMMAND_SIGNATURE)
            ->expectsOutput('Found 1 payable events.')
            ->expectsOutput('In that, there are 1 recipients')
            ->expectsOutput('and a license fee of 500 clicks payable to 0001-00000002-BB2D')
            ->expectsOutput('and a community fee of 125 clicks payable to 0001-00000024-FF89')
            ->assertExitCode(0);

        $event->refresh();

        self::assertNotEmpty($event->payment_id);
        self::assertEquals(500, $event->license_fee);
        self::assertEquals(250, $event->operator_fee);
        self::assertEquals(125, $event->community_fee);
        self::assertEquals(125, $event->paid_amount);

        $payments = Payment::all();
        self::assertCount(3, $payments);// adserver + license + community

        $payments->each(function (Payment $payment) {
            self::assertNotEmpty($payment->account_address);
            self::assertEquals(Payment::STATE_NEW, $payment->state);
            $payment->events->each(function (EventLog $entry) use ($payment) {
                self::assertEquals($entry->pay_to, $payment->account_address);
            });
        });
    }

    public function testHandleConversionFees(): void
    {
        Config::updateAdminSettings([Config::OPERATOR_TX_FEE => 0.5]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $communityFeeReader = self::createMock(CommunityFeeReader::class);
        $communityFeeReader->method('getAddress')->willReturn(new AccountId('0001-00000024-FF89'));
        $communityFeeReader->method('getFee')->willReturn(1 / 3);
        $this->instance(CommunityFeeReader::class, $communityFeeReader);
        $licenseReader = self::createMock(LicenseReader::class);
        $licenseReader->method('getAddress')->willReturn(new AccountId('0001-00000002-BB2D'));
        $licenseReader->method('getFee')->willReturn(0.1);
        $this->instance(LicenseReader::class, $licenseReader);
        /** @var Conversion $event */
        $event = Conversion::factory()->create([
            'event_value_currency' => 1000,
            'exchange_rate' => 1,
            'event_value' => 1000,
            'payment_status' => AdPayPaymentReportProcessor::STATUS_PAYMENT_ACCEPTED,
            'pay_to' => new AccountId('0001-00000001-8B4E'),
        ]);

        $this->artisan(DemandPreparePayments::COMMAND_SIGNATURE)
            ->expectsOutput('Found 1 payable conversions.')
            ->expectsOutput('In that, there are 1 recipients')
            ->expectsOutput('and a license fee of 100 clicks payable to 0001-00000002-BB2D')
            ->expectsOutput('and a community fee of 150 clicks payable to 0001-00000024-FF89')
            ->assertExitCode(0);

        $event->refresh();

        self::assertNotEmpty($event->payment_id);
        self::assertEquals(100, $event->license_fee);
        self::assertEquals(450, $event->operator_fee);
        self::assertEquals(150, $event->community_fee);
        self::assertEquals(300, $event->paid_amount);

        $payments = Payment::all();
        self::assertCount(3, $payments);// adserver + license + community

        $payments->each(function (Payment $payment) {
            self::assertNotEmpty($payment->account_address);
            self::assertEquals(Payment::STATE_NEW, $payment->state);
            $payment->events->each(function (EventLog $entry) use ($payment) {
                self::assertEquals($entry->pay_to, $payment->account_address);
            });
        });
    }
}
