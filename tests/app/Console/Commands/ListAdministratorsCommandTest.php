<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Illuminate\Support\Facades\Artisan;

class ListAdministratorsCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:admin:list';

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::COMMAND_SIGNATURE)
            ->expectsOutput('Command ops:admin:list already running')
            ->assertExitCode(1);
    }

    public function testNoAdministrators(): void
    {
        $this->artisan(self::COMMAND_SIGNATURE)
            ->expectsOutput('No administrators')
            ->assertExitCode(0);
    }

    public function testTwoAdministrators(): void
    {
        $emails = User::factory()->admin()->count(2)->create()->pluck('email');

        $exitCode = $this->withoutMockingConsoleOutput()->artisan(self::COMMAND_SIGNATURE);
        $output = Artisan::output();

        self::assertEquals(0, $exitCode);
        foreach ($emails as $email) {
            self::assertStringContainsString($email, $output);
        }
    }
}
