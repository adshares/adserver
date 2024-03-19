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

class AdSelectCaseExportCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:adselect:case:export';

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())
            ->method('lock')
            ->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        self::artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(1);
    }

    public function testHandle(): void
    {
        $this->app->bind(
            AdSelectCaseExporter::class,
            function () {
                $adSelectCaseExporter = self::createMock(AdSelectCaseExporter::class);
                $adSelectCaseExporter
                    ->method('getCaseIdToExport')
                    ->willReturn(0);
                $adSelectCaseExporter
                    ->method('exportCases')
                    ->willReturn(12);
                $adSelectCaseExporter
                    ->method('getCaseClickIdToExport')
                    ->willReturn(0);
                $adSelectCaseExporter
                    ->method('exportCaseClicks')
                    ->willReturn(1);
                return $adSelectCaseExporter;
            }
        );

        self::artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0)
            ->expectsOutputToContain('Exported 12 cases')
            ->expectsOutputToContain('Exported 1 click events');
    }

    public function testHandleErrors(): void
    {
        $this->app->bind(
            AdSelectCaseExporter::class,
            function () {
                $adSelectCaseExporter = self::createMock(AdSelectCaseExporter::class);
                $adSelectCaseExporter
                    ->method('getCaseIdToExport')
                    ->willThrowException(new UnexpectedClientResponseException('Getting case id failed'));
                $adSelectCaseExporter
                    ->method('getCaseClickIdToExport')
                    ->willThrowException(new UnexpectedClientResponseException('Getting case click id failed'));
                return $adSelectCaseExporter;
            }
        );

        self::artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0)
            ->expectsOutputToContain('Getting case id failed')
            ->expectsOutputToContain('Getting case click id failed');
    }
}
