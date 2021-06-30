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

namespace Adshares\Adserver\Tests\Client\Mapper\AdPay;

use Adshares\Adserver\Client\Mapper\AdPay\DemandBidStrategyMapper;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Support\Collection;

final class DemandCampaignMapperTest extends TestCase
{
    public function testMappingIds(): void
    {
        $user = factory(User::class)->create();
        /** @var Campaign $campaign */
        $campaign = factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_INACTIVE]);
        $expected = [$campaign->uuid];
        $collection = new Collection([$campaign]);

        self::assertEquals($expected, DemandBidStrategyMapper::mapBidStrategyCollectionToIds($collection));
    }
}
