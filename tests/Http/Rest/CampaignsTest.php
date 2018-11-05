<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Rest;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class CampaignsTest extends TestCase
{
    use RefreshDatabase;
    private const URI = '/api/campaigns';

    public function testEmptyDb()
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::URI . '/1');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteCampaignWithBanner()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $campaignId = $this->createCampaign($user);
        $bannerId = $this->createBanner($campaignId);

        $this->assertCount(1, Campaign::where('id', $campaignId)->get());
        $this->assertCount(1, Banner::where('id', $bannerId)->get());

        $response = $this->deleteJson(self::URI . "/{$campaignId}");
        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(0, Campaign::where('id', $campaignId)->get());
        $this->assertCount(0, Banner::where('id', $bannerId)->get());
        $this->assertCount(1, Campaign::withTrashed()->where('id', $campaignId)->get());
        $this->assertCount(1, Banner::withTrashed()->where('id', $bannerId)->get());

        $response = $this->deleteJson(self::URI . "/{$campaignId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFailDeleteNotOwnedCampaign()
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $user = factory(User::class)->create();
        $campaignId = $this->createCampaign($user);
        $this->createBanner($campaignId);

        $response = $this->deleteJson(self::URI . "/{$campaignId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private function createCampaign($user)
    {
        $campaign = factory(Campaign::class)->create(['user_id' => $user->id]);
        $campaignId = $campaign->id;
        return $campaignId;
    }

    private function createBanner($campaignId)
    {
        $banner = factory(Banner::class)->create(['campaign_id' => $campaignId]);
        $bannerId = $banner->id;
        return $bannerId;
    }
}
