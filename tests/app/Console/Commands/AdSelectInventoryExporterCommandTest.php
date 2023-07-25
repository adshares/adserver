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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Repository\Supply\NetworkCampaignRepository;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use Adshares\Supply\Domain\Model\CampaignCollection;

class AdSelectInventoryExporterCommandTest extends ConsoleTestCase
{
    public function testExport(): void
    {
        $repositoryMock = $this->createMock(NetworkCampaignRepository::class);
        $repositoryMock->expects(self::once())
            ->method('fetchActiveCampaigns')
            ->willReturn(new CampaignCollection());
        $repositoryMock->expects(self::once())
            ->method('fetchCampaignsToDelete')
            ->willReturn(new CampaignCollection());
        $this->instance(NetworkCampaignRepository::class, $repositoryMock);
        $exporterMock = $this->createMock(AdSelectInventoryExporter::class);
        $exporterMock->expects(self::once())
            ->method('export');
        $this->instance(AdSelectInventoryExporter::class, $exporterMock);

        $this->artisan('ops:adselect:inventory:export')
            ->assertExitCode(0);
    }

    public function testLock(): void
    {
        $repositoryMock = $this->createMock(NetworkCampaignRepository::class);
        $repositoryMock->expects(self::never())
            ->method('fetchActiveCampaigns');
        $repositoryMock->expects(self::never())
            ->method('fetchCampaignsToDelete');
        $this->instance(NetworkCampaignRepository::class, $repositoryMock);
        $exporterMock = $this->createMock(AdSelectInventoryExporter::class);
        $exporterMock->expects(self::never())
            ->method('export');
        $this->instance(AdSelectInventoryExporter::class, $exporterMock);
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())
            ->method('lock')
            ->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        self::artisan('ops:adselect:inventory:export')
            ->assertExitCode(1);
    }
}
