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

use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\ValueObject\Status;
use Illuminate\Support\Facades\Config;

final class InventoryImporterCommandTest extends ConsoleTestCase
{
    public function testImport(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D']);
        NetworkHost::factory()->create(['address' => '0001-00000003-AB0C']);
        NetworkHost::factory()->create(['address' => '0001-00000005-CBCA']);

        $this->artisan('ops:demand:inventory:import')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000002-BB2D')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000003-AB0C')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000005-CBCA')
            ->expectsOutput('[Inventory Importer] Finished importing data from 3/3 inventories')
            ->assertExitCode(0);
    }

    public function testWhitelistImport(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D']);
        NetworkHost::factory()->create(['address' => '0001-00000003-AB0C']);
        NetworkHost::factory()->create(['address' => '0001-00000005-CBCA']);

        Config::set('app.inventory_import_whitelist', ['0001-00000003-AB0C', '0001-00000005-CBCA']);
        $this->artisan('ops:demand:inventory:import')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000003-AB0C')
            ->expectsOutput('[Inventory Importer] Importing inventory from 0001-00000005-CBCA')
            ->expectsOutput('[Inventory Importer] Finished importing data from 2/2 inventories')
            ->doesntExpectOutput('[Inventory Importer] Importing inventory from 0001-00000002-BB2D')
            ->assertExitCode(0);

        Config::set('app.inventory_import_whitelist', ['0001-00000004-DBEB']);
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
}
