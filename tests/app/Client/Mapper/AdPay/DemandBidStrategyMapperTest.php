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
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Support\Collection;

final class DemandBidStrategyMapperTest extends TestCase
{
    public function testMappingArray(): void
    {
        $expected = [
            [
                'id' => '0123456789abcdef0123456789abcdef',
                'name' => 'name',
                'details' => [
                    [
                        'category' => 'user:country:st',
                        'rank' => 0.3,
                    ],
                ],
            ],
        ];

        $bidStrategyDetail = new BidStrategyDetail();
        $bidStrategyDetail->category = $expected[0]['details'][0]['category'];
        $bidStrategyDetail->rank = $expected[0]['details'][0]['rank'];
        $bidStrategy = new BidStrategy();
        $bidStrategy->uuid = $expected[0]['id'];
        $bidStrategy->name = $expected[0]['name'];
        $bidStrategy->bidStrategyDetails = new Collection([$bidStrategyDetail]);
        $collection = new Collection([$bidStrategy]);

        self::assertEquals($expected, DemandBidStrategyMapper::mapBidStrategyCollectionToArray($collection));
    }

    public function testMappingIds(): void
    {
        $expected = [
            '0123456789abcdef0123456789abcdef',
        ];

        $bidStrategyDetail = new BidStrategyDetail();
        $bidStrategyDetail->category = 'user:country:st';
        $bidStrategyDetail->rank = 0.3;
        $bidStrategy = new BidStrategy();
        $bidStrategy->uuid = $expected[0];
        $bidStrategy->name = 'name';
        $bidStrategy->bidStrategyDetails = new Collection([$bidStrategyDetail]);
        $collection = new Collection([$bidStrategy]);

        self::assertEquals($expected, DemandBidStrategyMapper::mapBidStrategyCollectionToIds($collection));
    }
}
