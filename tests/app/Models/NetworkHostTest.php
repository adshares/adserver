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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;

class NetworkHostTest extends TestCase
{
    public function testNonExistentHostsAddresses(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000001-8B4E']);
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D']);
        NetworkHost::factory()->create(['address' => '0001-00000003-AB0C', 'deleted_at' => new DateTimeImmutable()]);

        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000001-8B4E',
            'status' => Status::STATUS_ACTIVE
        ]);
        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000003-AB0C',
            'status' => Status::STATUS_ACTIVE
        ]);
        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000004-DBEB',
            'status' => Status::STATUS_ACTIVE
        ]);
        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000005-CBCA',
            'status' => Status::STATUS_DELETED
        ]);

        $addresses = NetworkHost::findNonExistentHostsAddresses();
        $this->assertEquals(['0001-00000003-AB0C', '0001-00000004-DBEB'], $addresses);
    }

    public function testWhitelistedAddresses(): void
    {
        NetworkHost::factory()->create(['address' => '0001-00000001-8B4E']);
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D']);
        NetworkHost::factory()->create(['address' => '0001-00000003-AB0C']);

        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000001-8B4E',
            'status' => Status::STATUS_ACTIVE
        ]);
        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000002-BB2D',
            'status' => Status::STATUS_ACTIVE
        ]);
        NetworkCampaign::factory()->create([
            'source_address' => '0001-00000004-DBEB',
            'status' => Status::STATUS_ACTIVE
        ]);

        $addresses = NetworkHost::findNonExistentHostsAddresses([
            '0001-00000001-8B4E',
            '0001-00000003-AB0C',
            '0001-00000004-DBEB'
        ]);
        $this->assertEquals(['0001-00000002-BB2D', '0001-00000004-DBEB'], $addresses);
    }

    public function testRegisterWhenHostInUnreachableState(): void
    {
        $hostData = [
            'address' => '0001-00000001-8B4E',
            'failed_connection' => 10,
            'status' => HostStatus::Unreachable,
        ];
        /** @var NetworkHost $host */
        $host = NetworkHost::factory()->create($hostData);
        NetworkHost::registerHost('0001-00000001-8B4E', $host->info_url, $host->info, new DateTimeImmutable());

        self::assertDatabaseHas(NetworkHost::class, $hostData);
    }

    public function testHandleWhitelist(): void
    {
        $host1 = NetworkHost::factory()->create([
            'address' => '0001-00000001-8B4E',
            'status' => HostStatus::Operational,
        ]);
        $host2 = NetworkHost::factory()->create([
            'address' => '0001-00000002-BB2D',
            'status' => HostStatus::Excluded,
        ]);
        $host3 = NetworkHost::factory()->create([
            'address' => '0001-00000003-AB0C',
            'status' => HostStatus::Operational,
        ]);
        $host4 = NetworkHost::factory()->create([
            'address' => '0001-00000004-DBEB',
            'status' => HostStatus::Excluded,
        ]);
        $host5 = NetworkHost::factory()->create([
            'address' => '0001-00000005-CBCA',
            'status' => HostStatus::Failure,
        ]);
        Config::updateAdminSettings([Config::INVENTORY_IMPORT_WHITELIST => '0001-00000001-8B4E,0001-00000002-BB2D']);
        DatabaseConfigReader::overwriteAdministrationConfig();

        NetworkHost::handleWhitelist();

        self::assertEquals(HostStatus::Operational, $host1->refresh()->status);
        self::assertEquals(HostStatus::Initialization, $host2->refresh()->status);
        self::assertEquals(HostStatus::Excluded, $host3->refresh()->status);
        self::assertEquals(HostStatus::Excluded, $host4->refresh()->status);
        self::assertEquals(HostStatus::Failure, $host5->refresh()->status);
    }

    public function testFetchUnreachableHostsForImportingInventory(): void
    {
        NetworkHost::factory()->count(3)
            ->state(
                new Sequence(
                    ['address' => '0001-00000001-8B4E', 'failed_connection' => 10],
                    ['address' => '0001-00000002-BB2D', 'failed_connection' => 20],
                    ['address' => '0001-00000003-AB0C', 'failed_connection' => 10],
                )
            )
            ->create([
                'last_synchronization' => new DateTimeImmutable('-2 days'),
                'last_synchronization_attempt' => new DateTimeImmutable('-70 minutes'),
                'status' => HostStatus::Unreachable,
            ]);

        $hosts = NetworkHost::fetchUnreachableHostsForImportingInventory(['0001-00000001-8B4E', '0001-00000002-BB2D']);

        self::assertCount(1, $hosts);
        self::assertEquals('0001-00000001-8B4E', $hosts->first()->address);
    }
}
