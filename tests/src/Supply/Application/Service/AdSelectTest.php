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

declare(strict_types=1);

namespace Adshares\Tests\Supply\Application\Service;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Mock\Client\DummyAdSelectClient;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\ValueObject\Status;

class AdSelectTest extends TestCase
{
    public function testFindBanners(): void
    {
        $this->app->bind(
            AdSelect::class,
            function () {
                return new DummyAdSelectClient();
            }
        );

        $user = factory(User::class)->create();
        $site = factory(Site::class)->create(['user_id' => $user->id]);
        $zone = factory(Zone::class)->create(['site_id' => $site->id]);

        $campaign =
            factory(NetworkCampaign::class)->create(
                ['status' => Status::STATUS_ACTIVE, 'publisher_id' => $user->uuid]
            );
        $banner = factory(NetworkBanner::class)->create(
            [
                'network_campaign_id' => $campaign->id,
                'status' => Status::STATUS_ACTIVE,
                'size' => $zone->size,
            ]
        );

        $zones = [['size' => $zone->size, 'zone' => $zone->uuid]];
        $bannerChecksum = $banner->checksum;

        $finder = $this->app->make(AdSelect::class);
        $banners = $finder->findBanners($zones, new ImpressionContext([], [], []));

        self::assertGreaterThan(0, count($banners));
        $this->assertEquals($bannerChecksum, $banners[0]['creative_sha1']);
    }
}
