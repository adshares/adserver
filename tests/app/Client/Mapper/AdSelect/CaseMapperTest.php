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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AdSelect\CaseMapper;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Tests\TestCase;

final class CaseMapperTest extends TestCase
{
    public function testMappingCase(): void
    {
        /** @var NetworkImpression $networkImpression */
        $networkImpression = NetworkImpression::factory()->create([
            'human_score' => 0.7,
            'impression_id' => 'f9bfda042c7931219f9b50ea77acb7d4',
            'page_rank' => 0.5,
            'tracking_id' => 'b885328541df70566c5be71bf1348a8d',
            'user_data' => [
                'interest' => ['200063', '200142'],
                'javascript' => [true],
                'browser' => ['Firefox'],
                'human_score' => [0.5],
            ],
            'user_id' => '566c5be71bf1348a8db885328541df70',
        ]);
        /** @var NetworkCase $networkCase */
        $networkCase = NetworkCase::factory()->create([
            'banner_id' => 'fc3ac3ba7d9934119ae6ee0f9aa8e5cd',
            'campaign_id' => '312064267be539d4a73d70ade6d08139',
            'created_at' => '2022-08-16T16:37:26+00:00',
            'network_impression_id' => $networkImpression->id,
            'publisher_id' => '328541df70566c5be71bf1348a8db885',
            'site_id' => '10000000000000000000000000000001',
            'zone_id' => '20000000000000000000000000000002',
        ]);
        $caseWithImpression = NetworkCase::fetchCasesToExport(0, 1)->first();
        $expected = [
            'id' => $networkCase->id,
            'created_at' => '2022-08-16T16:37:26+00:00',
            'publisher_id' => '328541df70566c5be71bf1348a8db885',
            'site_id' => '10000000000000000000000000000001',
            'zone_id' => '20000000000000000000000000000002',
            'campaign_id' => '312064267be539d4a73d70ade6d08139',
            'banner_id' => 'fc3ac3ba7d9934119ae6ee0f9aa8e5cd',
            'impression_id' => 'f9bfda042c7931219f9b50ea77acb7d4',
            'tracking_id' => 'b885328541df70566c5be71bf1348a8d',
            'user_id' => '566c5be71bf1348a8db885328541df70',
            'human_score' => 0.7,
            'page_rank' => 0.5,
            'keywords' => [
                'interest' => ['200063', '200142'],
                'javascript' => [true],
                'browser' => ['Firefox'],
                'human_score' => [0.5],
            ],
        ];

        $this->assertEquals($expected, CaseMapper::map($caseWithImpression));
    }
}
