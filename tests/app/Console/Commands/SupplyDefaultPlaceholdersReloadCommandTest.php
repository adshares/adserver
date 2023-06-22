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
use Adshares\Adserver\Services\Supply\DefaultBannerPlaceholderGenerator;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;

class SupplyDefaultPlaceholdersReloadCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:supply:default-placeholders:reload';

    public function testReload(): void
    {
        $generator = self::createMock(DefaultBannerPlaceholderGenerator::class);
        $generator->expects(self::once())->method('generate')->with(true);
        $this->app->bind(DefaultBannerPlaceholderGenerator::class, fn() => $generator);

        $this->artisan(self::SIGNATURE)
            ->assertExitCode(0);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)
            ->expectsOutput('Command ops:supply:default-placeholders:reload already running')
            ->assertExitCode(1);
    }
}
