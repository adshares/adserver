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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;

class BannerClassificationsRequestCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:demand:classification:request';

    public function testHandle(): void
    {
        $classifierRepositoryMock = self::createMock(ClassifierExternalRepository::class);
        $classifierRepositoryMock->expects(self::once())
            ->method('fetchClassifierByName')
            ->with('test')
            ->willReturn(new ClassifierExternal('test', 'key', 'https://example.com', 'api-key', 'api-secret'));
        $this->app->bind(ClassifierExternalRepository::class, fn() => $classifierRepositoryMock);
        $classifier = self::createMock(ClassifierExternalClient::class);
        $classifier->expects(self::once())
            ->method('requestClassification');
        $this->app->bind(ClassifierExternalClient::class, fn() => $classifier);
        /** @var Banner $banner */
        $banner = Banner::factory()->create();
        $banner->classifications()->save(BannerClassification::prepare('test'));

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(1);
    }
}
