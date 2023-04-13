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

use Adshares\Adserver\Console\Commands\AdPayGetPayments;
use Adshares\Adserver\Console\Commands\DemandSendPayments;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Exceptions\ConsoleCommandException;
use Adshares\Adserver\Mail\TechnicalError;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\PaymentReport;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Mock\Console\Kernel as KernelMock;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Exception\LogicException;

class DemandProcessPaymentsTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:demand:payments:process';

    public function testNoExportedEvents(): void
    {
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        Event::assertNotDispatched(ServerEvent::class);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        Event::assertNotDispatched(ServerEvent::class);
    }

    public function testHandleFailWhileExchangeRateNotAvailable(): void
    {
        self::setupExportTime();
        $this->setupConsoleKernel(
            self::commandValues(['ops:adpay:payments:get' => new ExchangeRateNotAvailableException('text-exception')])
        );

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        self::assertEquals(PaymentReport::STATUS_NEW, PaymentReport::first()->status);
        Event::assertNotDispatched(ServerEvent::class);
        Mail::assertQueued(TechnicalError::class);
    }

    public function testHandleFailWhileInvalidOptionFrom(): void
    {
        self::expectException(InvalidArgumentException::class);

        $this->artisan(self::SIGNATURE, ['--from' => 'invalid']);
    }

    public function testHandleFailWhileIdsNotExist(): void
    {
        self::expectException(ConsoleCommandException::class);

        $this->artisan(self::SIGNATURE, ['--ids' => 'invalid']);
    }

    public function testHandleRecalculate(): void
    {
        self::setupExportTime();
        $this->setupConsoleKernel(self::commandValues());
        $paymentReport = PaymentReport::first();
        $updatedAt = $paymentReport->updated_at;

        $this->artisan(self::SIGNATURE, ['--ids' => (string)$paymentReport->id])
            ->assertExitCode(0);
        self::assertGreaterThan($updatedAt, $paymentReport->refresh()->updated_at);
    }

    public function testHandleProcessOldPaymentReport(): void
    {
        self::setupExportTime();
        $this->setupConsoleKernel(self::commandValues());
        $id = DateUtils::roundTimestampToHour((new DateTimeImmutable('-5 days'))->getTimestamp());
        PaymentReport::register($id);
        $paymentReport = PaymentReport::fetchById($id);
        $paymentReport->status = PaymentReport::STATUS_NEW;
        $paymentReport->save();
        $from = (new DateTimeImmutable('-6 days'))->format('d.m.Y');

        $this->artisan(self::SIGNATURE, ['--from' => $from])
            ->assertExitCode(0);
        self::assertEquals(PaymentReport::STATUS_DONE, $paymentReport->refresh()->status);
    }

    public function testHandleSkipOldPaymentReport(): void
    {
        self::setupExportTime();
        $this->setupConsoleKernel(self::commandValues());
        $id = DateUtils::roundTimestampToHour((new DateTimeImmutable('-5 days'))->getTimestamp());
        PaymentReport::register($id);
        $paymentReport = PaymentReport::fetchById($id);
        $paymentReport->status = PaymentReport::STATUS_NEW;
        $paymentReport->save();

        $this->artisan(self::SIGNATURE)
            ->assertExitCode(0);
        self::assertEquals(PaymentReport::STATUS_NEW, $paymentReport->refresh()->status);
    }

    /**
     * @dataProvider reportStatusProvider
     *
     * @param array $commandReturnValues
     * @param int $expectedPaymentReportStatus
     * @param bool $sentPayment
     */
    public function testReportStatus(
        array $commandReturnValues,
        int $expectedPaymentReportStatus,
        bool $sentPayment
    ): void {
        self::setupExportTime();
        $this->setupConsoleKernel($commandReturnValues);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        self::assertEquals($expectedPaymentReportStatus, PaymentReport::first()->status);

        if ($sentPayment) {
            self::assertServerEventDispatched(ServerEventType::OutgoingAdPaymentProcessed);
        } else {
            Event::assertNotDispatched(ServerEvent::class);
        }
    }

    public function reportStatusProvider(): array
    {
        return [
            'all ok' => [
                self::commandValues(),
                PaymentReport::STATUS_DONE,
                true,
            ],
            'get locked' => [
                self::commandValues(['ops:adpay:payments:get' => new LogicException('text-exception')]),
                PaymentReport::STATUS_NEW,
                false,
            ],
            'get client exception' => [
                self::commandValues(['ops:adpay:payments:get' => AdPayGetPayments::STATUS_CLIENT_EXCEPTION]),
                PaymentReport::STATUS_NEW,
                false,
            ],
            'get failed' => [
                self::commandValues(['ops:adpay:payments:get' => AdPayGetPayments::STATUS_REQUEST_FAILED]),
                PaymentReport::STATUS_ERROR,
                false,
            ],
            'prepare locked' => [
                self::commandValues(['ops:demand:payments:prepare' => new LogicException('text-exception')]),
                PaymentReport::STATUS_UPDATED,
                false,
            ],
            'send ads error' => [
                self::commandValues(['ops:demand:payments:send' => DemandSendPayments::STATUS_ERROR_ADS]),
                PaymentReport::STATUS_PREPARED,
                false,
            ],
            'send locked' => [
                self::commandValues(['ops:demand:payments:send' => new LogicException('text-exception')]),
                PaymentReport::STATUS_PREPARED,
                false,
            ],
            'aggregate locked' => [
                self::commandValues(['ops:stats:aggregate:advertiser' => new LogicException('text-exception')]),
                PaymentReport::STATUS_DONE,
                true,
            ],
        ];
    }

    private static function setupExportTime(): void
    {
        Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME, new DateTimeImmutable());
        Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME, new DateTimeImmutable());
    }

    private function setupConsoleKernel(array $commandReturnValues): void
    {
        $this->app->singleton(Kernel::class, KernelMock::class);
        $this->app[Kernel::class]->setCommandReturnValues($commandReturnValues);
    }

    private static function commandValues(array $merge = []): array
    {
        return array_merge(
            [
                'ops:adpay:payments:get' => 0,
                'ops:demand:payments:prepare' => 0,
                'ops:demand:payments:send' => 0,
                'ops:stats:aggregate:advertiser' => 0,
            ],
            $merge
        );
    }
}
