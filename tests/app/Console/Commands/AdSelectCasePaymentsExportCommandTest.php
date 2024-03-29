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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Services\Supply\AdSelectCaseExporter;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;

class AdSelectCasePaymentsExportCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:adselect:case-payments:export';

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testExport(): void
    {
        $this->app->bind(
            AdSelectCaseExporter::class,
            function () {
                $adSelectCaseExporter = self::createMock(AdSelectCaseExporter::class);
                $adSelectCaseExporter->method('exportCasePayments')->willReturn(1);
                $adSelectCaseExporter->method('exportBoostPayments')->willReturn(2);
                return $adSelectCaseExporter;
            }
        );

        $this->artisan(self::SIGNATURE)
            ->expectsOutputToContain('[AdSelectCaseExport] Exported 1 payment(s)')
            ->expectsOutputToContain('[AdSelectCaseExport] Exported 2 boost payment(s)')
            ->assertExitCode(0);
    }

    public function testExportAdSelectError(): void
    {
        $this->app->bind(
            AdSelectCaseExporter::class,
            function () {
                $adSelectCaseExporter = self::createMock(AdSelectCaseExporter::class);
                $adSelectCaseExporter
                    ->method('exportCasePayments')
                    ->willThrowException(new UnexpectedClientResponseException('test1'));
                $adSelectCaseExporter
                    ->method('exportBoostPayments')
                    ->willThrowException(new UnexpectedClientResponseException('test2'));
                return $adSelectCaseExporter;
            }
        );

        $this->artisan(self::SIGNATURE)
            ->expectsOutputToContain('test1')
            ->expectsOutputToContain('test2')
            ->assertExitCode(0);
    }
}
