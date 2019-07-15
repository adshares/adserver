<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

use Adshares\Adserver\Tests\Console\TestCase;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

final class InventoryImporterCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNoHosts(): void
    {
        $mockNetworkHost = Mockery::mock('alias:Adshares\Adserver\Models\NetworkHost');
        $mockNetworkHost->shouldReceive('findNonExistentHostsAddresses')->andReturn([]);
        $mockNetworkHost->shouldReceive('fetchHosts')->andReturn(new Collection());

        $this->artisan('ops:demand:inventory:import')->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNonExistentHosts(): void
    {
        $sourceAddress = '0001-00000001-8B4E';

        $mockNetworkHost = Mockery::mock('alias:Adshares\Adserver\Models\NetworkHost');
        $mockNetworkHost->shouldReceive('findNonExistentHostsAddresses')->andReturn([$sourceAddress]);
        $mockNetworkHost->shouldReceive('fetchHosts')->andReturn(new Collection());

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
