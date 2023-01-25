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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Console\Command\Command;

class UpsertConfigurationCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'config:upsert';

    public function testHandleWithKeyAndValueArguments(): void
    {
        self::artisan(join(' ', [self::COMMAND_SIGNATURE, 'support-email', 'custom@example.com']))
            ->assertExitCode(Command::SUCCESS);

        self::assertDatabaseHas(Config::class, ['key' => 'support-email', 'value' => 'custom@example.com']);
    }

    public function testHandleWithKeyAndValueZeroArguments(): void
    {
        self::artisan(join(' ', [self::COMMAND_SIGNATURE, 'auto-confirmation-enabled', '0']))
            ->assertExitCode(Command::SUCCESS);

        self::assertDatabaseHas(Config::class, ['key' => 'auto-confirmation-enabled', 'value' => '0']);
    }

    public function testHandleWithKeyArgument(): void
    {
        self::artisan(join(' ', [self::COMMAND_SIGNATURE, 'adshares-secret']))
            ->expectsQuestion(
                'Set value of adshares-secret',
                '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF',
            )
            ->assertExitCode(Command::SUCCESS);

        self::assertDatabaseMissing(
            Config::class,
            ['value' => '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF'],
        );
        self::assertEquals(
            '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF',
            Crypt::decryptString(config('app.adshares_secret')),
        );
    }

    public function testLock(): void
    {
        $lockerMock = self::createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        self::artisan(join(' ', [self::COMMAND_SIGNATURE, 'adshares-secret']))
            ->expectsOutput('Command config:upsert already running')
            ->assertExitCode(Command::FAILURE);
    }
}
