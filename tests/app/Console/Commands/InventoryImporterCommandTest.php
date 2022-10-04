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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\InventoryImporter;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTimeImmutable;

final class InventoryImporterCommandTest extends ConsoleTestCase
{
    public function testImport(): void
    {
        $testStartTime = new DateTimeImmutable();
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D', 'status' => HostStatus::Initialization]);
        NetworkHost::factory()->create(['address' => '0001-00000003-AB0C']);
        NetworkHost::factory()->create(['address' => '0001-00000004-DBEB', 'deleted_at' => new DateTimeImmutable()]);
        NetworkHost::factory()->create(['address' => '0001-00000005-CBCA']);

        $this->artisan('ops:demand:inventory:import')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000002-BB2D')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000003-AB0C')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000005-CBCA')
            ->expectsOutput('[Inventory Importer] Finished importing data from 3/3 inventories')
            ->assertExitCode(0);
        self::assertDatabaseHas(
            NetworkHost::class,
            [
                'address' => '0001-00000002-BB2D',
                'status' => HostStatus::Operational,
            ]
        );
        $host = NetworkHost::fetchByAddress('0001-00000002-BB2D');
        self::assertNotNull($host->last_synchronization);
        self::assertGreaterThanOrEqual($testStartTime->getTimestamp(), $host->last_synchronization->getTimestamp());
    }

    public function testWhitelistImport(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D']);
        NetworkHost::factory()->create(['address' => '0001-00000003-AB0C']);
        NetworkHost::factory()->create(['address' => '0001-00000005-CBCA']);

        Config::updateAdminSettings([Config::INVENTORY_IMPORT_WHITELIST => '0001-00000003-AB0C,0001-00000005-CBCA']);
        $this->artisan('ops:demand:inventory:import')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000003-AB0C')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000005-CBCA')
            ->expectsOutput('[Inventory Importer] Finished importing data from 2/2 inventories')
            ->doesntExpectOutput('[Inventory Importer] Importing inventory from 0001-00000002-BB2D')
            ->assertExitCode(0);

        Config::updateAdminSettings([Config::INVENTORY_IMPORT_WHITELIST => '0001-00000004-DBEB']);
        $this->artisan('ops:demand:inventory:import')
            ->expectsOutput('[Inventory Importer] Stopped importing - no hosts found')
            ->assertExitCode(0);
    }

    public function testNoHosts(): void
    {
        $this->artisan('ops:demand:inventory:import')
            ->expectsOutput('[Inventory Importer] Stopped importing - no hosts found')
            ->assertExitCode(0);
    }

    public function testNonExistentHosts(): void
    {
        NetworkCampaign::factory()->create(['status' => Status::STATUS_ACTIVE]);

        $campaignRepository = $this->createMock(CampaignRepository::class);
        $campaignRepository->expects($this->once())->method('markedAsDeletedBySourceAddress');
        $this->app->bind(
            CampaignRepository::class,
            function () use ($campaignRepository) {
                return $campaignRepository;
            }
        );

        $this->artisan('ops:demand:inventory:import')->assertExitCode(0);
    }

    public function testLock(): void
    {
        $lockerMock = self::createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan('ops:demand:inventory:import')
            ->expectsOutputToContain('Supply inventory processing already running');
    }

    public function testRemoveNetworkHost(): void
    {
        /** @var NetworkHost $host */
        $host = NetworkHost::factory()->create(['address' => '0001-00000004-DBEB', 'failed_connection' => 9]);
        $inventoryImporter = self::createMock(InventoryImporter::class);
        $inventoryImporter->expects(self::once())->method('import')->willThrowException(
            new UnexpectedClientResponseException('test-exception')
        );
        $inventoryImporter->expects(self::once())->method('clearInventoryForHostAddress');
        $this->app->bind(InventoryImporter::class, fn() => $inventoryImporter);

        self::artisan('ops:demand:inventory:import')
            ->expectsOutputToContain('[Inventory Importer] Inventory (0001-00000004-DBEB) has been removed')
            ->expectsOutputToContain('[Inventory Importer] Finished importing data from 0/1 inventories');
        self::assertDatabaseHas(
            NetworkHost::class,
            [
                'address' => $host->address,
                'status' => HostStatus::Unreachable,
            ]
        );
    }

    public function testImportEmptyInventory(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000004-DBEB']);
        $inventoryImporter = self::createMock(InventoryImporter::class);
        $inventoryImporter->expects(self::once())->method('import')->willThrowException(
            new EmptyInventoryException('test-exception')
        );
        $inventoryImporter->expects(self::once())->method('clearInventoryForHostAddress');
        $this->app->bind(InventoryImporter::class, fn() => $inventoryImporter);

        self::artisan('ops:demand:inventory:import')
            ->expectsOutputToContain(
                '[Inventory Importer] Inventory (0001-00000004-DBEB) is empty. It has been removed from the database'
            )
            ->expectsOutputToContain('[Inventory Importer] Finished importing data from 0/1 inventories');
    }
}
