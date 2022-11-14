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
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use PDOException;

class CreateOauthClientCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:passport-client:create';

    public function testCreateClient(): void
    {
        $this->artisan(self::COMMAND_SIGNATURE, ['name' => 'Example', 'redirect_uri' => 'https://example.com/callback'])
            ->assertExitCode(0);

        self::assertDatabaseCount(Client::class, 1);
        self::assertDatabaseHas(Client::class, ['revoked' => 0]);

        $this->artisan(self::COMMAND_SIGNATURE, ['name' => 'Example', 'redirect_uri' => 'https://example.com/callback'])
            ->assertExitCode(0);

        self::assertDatabaseCount(Client::class, 2);
        self::assertDatabaseHas(Client::class, ['revoked' => 0]);
        self::assertDatabaseHas(Client::class, ['revoked' => 1]);
    }

    public function testCreateClientFail(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();

        $this->artisan(self::COMMAND_SIGNATURE, ['name' => 'Example', 'redirect_uri' => 'https://example.com/callback'])
            ->assertExitCode(1);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::COMMAND_SIGNATURE, ['name' => 'Example', 'redirect_uri' => 'https://example.com/callback'])
            ->expectsOutput('Command ops:passport-client:create already running')
            ->assertExitCode(1);
    }
}
