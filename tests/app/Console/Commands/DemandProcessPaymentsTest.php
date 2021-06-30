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

use Adshares\Adserver\Console\Commands\AdPayGetPayments;
use Adshares\Adserver\Console\Commands\DemandSendPayments;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\PaymentReport;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Mock\Console\Kernel as KernelMock;
use DateTime;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Exception\LogicException;

class DemandProcessPaymentsTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:demand:payments:process';

    public function testNoExportedEvents(): void
    {
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    /**
     * @dataProvider reportStatusProvider
     *
     * @param array $commandReturnValues
     * @param int $expectedPaymentReportStatus
     */
    public function testReportStatus(array $commandReturnValues, int $expectedPaymentReportStatus): void
    {
        self::setupExportTime();
        $this->setupConsoleKernel($commandReturnValues);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        self::assertEquals($expectedPaymentReportStatus, PaymentReport::first()->status);
    }

    public function reportStatusProvider(): array
    {
        return [
            'all ok' => [
                self::commandValues(),
                PaymentReport::STATUS_DONE,
            ],
            'get locked' => [
                self::commandValues(['ops:adpay:payments:get' => new LogicException('text-exception')]),
                PaymentReport::STATUS_NEW,
            ],
            'get client exception' => [
                self::commandValues(['ops:adpay:payments:get' => AdPayGetPayments::STATUS_CLIENT_EXCEPTION]),
                PaymentReport::STATUS_NEW,
            ],
            'get failed' => [
                self::commandValues(['ops:adpay:payments:get' => AdPayGetPayments::STATUS_REQUEST_FAILED]),
                PaymentReport::STATUS_ERROR,
            ],
            'prepare locked' => [
                self::commandValues(['ops:demand:payments:prepare' => new LogicException('text-exception')]),
                PaymentReport::STATUS_UPDATED,
            ],
            'send ads error' => [
                self::commandValues(['ops:demand:payments:send' => DemandSendPayments::STATUS_ERROR_ADS]),
                PaymentReport::STATUS_PREPARED,
            ],
            'send locked' => [
                self::commandValues(['ops:demand:payments:send' => new LogicException('text-exception')]),
                PaymentReport::STATUS_PREPARED,
            ],
            'aggregate locked' => [
                self::commandValues(['ops:stats:aggregate:advertiser' => new LogicException('text-exception')]),
                PaymentReport::STATUS_DONE,
            ],
        ];
    }

    private static function setupExportTime(): void
    {
        Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME, new DateTime());
        Config::upsertDateTime(Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME, new DateTime());
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
