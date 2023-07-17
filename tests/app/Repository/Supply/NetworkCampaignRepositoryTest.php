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

namespace Adshares\Adserver\Tests\Repository\Supply;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Repository\Supply\NetworkCampaignRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Domain\ValueObject\Status;

final class NetworkCampaignRepositoryTest extends TestCase
{
    public function testSave(): void
    {
        $campaign = (new DummyDemandClient())->fetchAllInventory(
            new AccountId('0001-00000004-DBEB'),
            'https://example.com',
            'https://app.example.com/inventory',
            false,
        )->get(0);
        $repository = new NetworkCampaignRepository();

        $repository->save($campaign);

        self::assertDatabaseCount(NetworkCampaign::class, 1);
        self::assertDatabaseCount(NetworkBanner::class, 3);
        self::assertDatabaseMissing(NetworkBanner::class, ['signed_at' => null]);
    }

    public function testFetchActiveCampaigns(): void
    {
        $campaign = NetworkCampaign::factory()->create(['status' => Status::STATUS_ACTIVE]);
        NetworkBanner::factory()->create(['network_campaign_id' => $campaign]);
        $repository = new NetworkCampaignRepository();

        $campaigns = $repository->fetchActiveCampaigns();

        self::assertCount(1, $campaigns);
        self::assertCount(1, $campaigns[0]->getBanners());
    }

    public function testFetchCampaignsToDelete(): void
    {
        $campaign = NetworkCampaign::factory()->create(['status' => Status::STATUS_TO_DELETE]);
        NetworkBanner::factory()->create(['network_campaign_id' => $campaign]);
        $repository = new NetworkCampaignRepository();

        $campaigns = $repository->fetchCampaignsToDelete();

        self::assertCount(1, $campaigns);
        self::assertCount(1, $campaigns[0]->getBanners());
    }
}
