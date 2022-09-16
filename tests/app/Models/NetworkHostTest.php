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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTimeImmutable;

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
}
