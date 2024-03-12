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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Domain\ValueObject\Status;

class NetworkCampaignTest extends TestCase
{
    public function testFetchActiveCampaignsFromHost(): void
    {
        $source = '0001-00000004-DBEB';
        $campaign1 = NetworkCampaign::factory()->create([
            'source_address' => $source,
        ]);
        NetworkBanner::factory()->create([
            'network_campaign_id' => $campaign1,
        ]);
        $campaign2 = NetworkCampaign::factory()->create();
        $campaign3 = NetworkCampaign::factory()->create([
            'source_address' => $source,
            'status' => Status::STATUS_DELETED,
        ]);
        $campaign4 = NetworkCampaign::factory()->create([
            'source_address' => $source,
        ]);
        $campaign5 = NetworkCampaign::factory()->create([
            'source_address' => $source,
        ]);
        NetworkBanner::factory()->create([
            'network_campaign_id' => $campaign5,
            'status' => Status::STATUS_DELETED,
        ]);

        $result = NetworkCampaign::fetchActiveCampaignsFromHost($source);

        $ids = $result->map(fn (NetworkCampaign $campaign) => $campaign->id)->toArray();
        self::assertContains($campaign1->id, $ids);
        self::assertNotContains($campaign2->id, $ids);
        self::assertNotContains($campaign3->id, $ids);
        self::assertNotContains($campaign4->id, $ids);
        self::assertNotContains($campaign5->id, $ids);
    }
}
