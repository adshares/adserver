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

final class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/campaigns';

    public function testBrowseCampaignWRequesthenNoCampaigns(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);
    }

    public function testCampaignRequestWhenCampaignIsNotFound(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI . '/1');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testCreateCampaignWithoutBannersAndTargeting(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, ['campaign' => $this->campaignInputData()]);
        $response->assertStatus(Response::HTTP_CREATED);

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testDeleteCampaignWithBanner(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $campaignId = $this->createCampaignForUser($user);
        $bannerId = $this->createBannerForCampaign($campaignId);

        $this->assertCount(1, Campaign::where('id', $campaignId)->get());
        $this->assertCount(1, Banner::where('id', $bannerId)->get());

        $response = $this->deleteJson(self::URI . "/{$campaignId}");
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertCount(0, Campaign::where('id', $campaignId)->get());
        $this->assertCount(0, Banner::where('id', $bannerId)->get());
        $this->assertCount(1, Campaign::withTrashed()->where('id', $campaignId)->get());
        $this->assertCount(1, Banner::withTrashed()->where('id', $bannerId)->get());

        $response = $this->deleteJson(self::URI . "/{$campaignId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFailDeleteNotOwnedCampaign(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $user = factory(User::class)->create();
        $campaignId = $this->createCampaignForUser($user);
        $this->createBannerForCampaign($campaignId);

        $response = $this->deleteJson(self::URI . "/{$campaignId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private function createCampaignForUser(User $user): int
    {
        $campaign = factory(Campaign::class)->create(['user_id' => $user->id]);
        $campaignId = $campaign->id;

        return $campaignId;
    }

    private function createBannerForCampaign(int $campaignId): int
    {
        $banner = factory(Banner::class)->create(['campaign_id' => $campaignId]);
        $bannerId = $banner->id;

        return $bannerId;
    }

    private function campaignInputData(): array
    {
        return [
            'basicInformation' => [
                'status' => 0,
			    'name' =>  'Adshares test campaign',
			    'targetUrl' => 'http://adshares.net',
			    'max_cpc' => 2,
			    'max_cpm' => 1,
			    'budget' => 100,
			    'dateStart' => '2018-01-01',
			    'dateEnd' => '2018-12-30',
            ],
            'targeting' => [
                'requires' => [],
                'excludes' => [],
            ],
            'targetingArray' => [
                'requires' => [],
                'excludes' => [],
            ],
            'ads' => [],
        ];
    }

    private function getIdFromLocation(string $location): string
    {
        $matches = [];
        $this->assertSame(1, preg_match('/(\d+)$/', $location, $matches));

        return $matches[1];
    }
}
