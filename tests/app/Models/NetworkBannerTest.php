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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Http\Request\Classifier\NetworkBannerFilter;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;

class NetworkBannerTest extends TestCase
{
    public function testFetchByFilterWhenNoSites(): void
    {
        $request = self::createMock(Request::class);
        $request->method('get')->willReturnCallback(fn(string $field, $default = null) => $default);
        $filter = new NetworkBannerFilter($request, 1, 2);

        $banners = NetworkBanner::fetchByFilter($filter, null, new Collection());

        self::assertEmpty($banners);
    }

    public function testFetchByFilterWhenExpectingDirectDealSuccess(): void
    {
        $campaign = NetworkCampaign::factory()->create(
            [
                'targeting_requires' => [
                    'site' => [
                        'domain' => [
                            'example.com',
                        ],
                    ],
                ],
            ]
        );
        NetworkBanner::factory()->create(
            [
                'network_campaign_id' => $campaign,
            ]
        );
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(
            [
                'domain' => 'example.com',
                'only_direct_deals' => true,
                'user_id' => $user,
            ]
        );
        $request = self::createMock(Request::class);
        $request->method('get')->willReturnCallback(fn(string $field, $default = null) => $default);
        $filter = new NetworkBannerFilter($request, $user->id, $site->id);

        $banners = NetworkBanner::fetchByFilter($filter, null, Site::fetchAll());

        self::assertCount(1, $banners);
    }

    public function testFetchByFilterWhenExpectingDirectDealFail(): void
    {
        $campaign = NetworkCampaign::factory()->create(
            [
                'targeting_requires' => [
                    'site' => [
                        'domain' => [
                            'fail-example.com',
                        ],
                    ],
                ],
            ]
        );
        NetworkBanner::factory()->create(
            [
                'network_campaign_id' => $campaign,
            ]
        );
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(
            [
                'domain' => 'example.com',
                'only_direct_deals' => true,
                'user_id' => $user,
            ]
        );
        $request = self::createMock(Request::class);
        $request->method('get')->willReturnCallback(fn(string $field, $default = null) => $default);
        $filter = new NetworkBannerFilter($request, $user->id, $site->id);

        $banners = NetworkBanner::fetchByFilter($filter, null, Site::fetchAll());

        self::assertEmpty($banners);
    }
}
