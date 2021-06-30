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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class SupplyControllerTest extends TestCase
{
    private const PAGE_WHY_URI = '/supply/why';

    public function testPageWhyNoParameters(): void
    {
        $response = $this->get(self::PAGE_WHY_URI);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPageWhyInvalidBannerId(): void
    {
        $response = $this->get(
            self::PAGE_WHY_URI . '?bid=0123456789abcdef&cid=0123456789abcdef0123456789abcdef'
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPageWhyNonExistentBannerId(): void
    {
        $response = $this->get(
            self::PAGE_WHY_URI . '?bid=0123456789abcdef0123456789abcdef&cid=0123456789abcdef0123456789abcdef'
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPageWhy(): void
    {
        $host = 'https://example.com';
        $campaignId = 1;
        factory(NetworkHost::class)->create(['host' => $host]);
        factory(NetworkCampaign::class)->create(['id' => $campaignId, 'source_host' => $host]);
        $banner = factory(NetworkBanner::class)->create(['id' => 1, 'network_campaign_id' => $campaignId]);

        $response = $this->get(self::PAGE_WHY_URI . '?bid=' . $banner->uuid . '&cid=0123456789abcdef0123456789abcdef');

        $response->assertStatus(Response::HTTP_OK);
    }
}
