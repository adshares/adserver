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
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Mock\Client\DummyAdUserClient;

class UpdateTargetingOptionsTest extends ConsoleTestCase
{
    public function testTargetingOptionsUpdate(): void
    {
        $this->app->bind(AdUser::class, DummyAdUserClient::class);

        $this->artisan('ops:targeting-options:update')
            ->assertExitCode(0);
        self::assertServerEventDispatched(ServerEventType::TargetingUpdated);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan('ops:targeting-options:update')
            ->expectsOutput('Command ops:targeting-options:update already running')
            ->assertExitCode(0);
    }
}
