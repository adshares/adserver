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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ScopeType;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

final class ApiCampaignsControllerTest extends TestCase
{
    private const URI_CAMPAIGNS = '/api/v2/campaigns';
    private const CAMPAIGN_DATA_STRUCTURE = [
        'id',
        'uuid',
        'createdAt',
        'updatedAt',
        'classifications' => [
            '*' => [
                'classifier',
                'status',
                'keywords',
            ],
        ],
        'secret',
        'conversionClick',
        'conversionClickLink',
        'basicInformation' => [
            'status',
            'name',
            'targetUrl',
            'maxCpc',
            'maxCpm',
            'budget',
            'medium',
            'vendor',
            'dateStart',
            'dateEnd',
        ],
        'targeting' => [
            'requires',
            'excludes',
        ],
        'ads' => [
            '*' => [
                'id',
                'uuid',
                'createdAt',
                'updatedAt',
                'creativeType',
                'creativeMime',
                'creativeSha1',
                'creativeSize',
                'name',
                'status',
                'cdnUrl',
                'url',
            ],
        ],
        'bidStrategy' => [
            'name',
            'uuid',
        ],
        'conversions' => [],
    ];
    private const CAMPAIGN_STRUCTURE = [
        'data' => self::CAMPAIGN_DATA_STRUCTURE,
    ];
    private const CAMPAIGNS_STRUCTURE = [
        'data' => [
            '*' => self::CAMPAIGN_DATA_STRUCTURE,
        ],
    ];

    public function testDeleteCampaignById(): void
    {
        $user = $this->setUpUser();
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'status' => Campaign::STATUS_ACTIVE,
            'user_id' => $user->id,
        ]);
        $conversion = ConversionDefinition::factory()->create(['campaign_id' => $campaign->id]);
        $banner = Banner::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => Banner::STATUS_ACTIVE,
        ]);
        $bannerClassification = $banner->classifications()->save(BannerClassification::prepare('test_classifier'));

        $response = $this->delete(self::buildUriCampaign($campaign->id));
        $response->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertTrue($campaign->refresh()->trashed());
        self::assertTrue($conversion->refresh()->trashed());
        self::assertTrue($banner->refresh()->trashed());
        self::assertEquals(Campaign::STATUS_INACTIVE, $campaign->status);
        self::assertDatabaseMissing(BannerClassification::class, ['id' => $bannerClassification->id]);
    }

    public function testDeleteCampaignByIdFail(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $user = $this->setUpUser();
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'status' => Campaign::STATUS_ACTIVE,
            'user_id' => $user->id,
        ]);

        $response = $this->delete(self::buildUriCampaign($campaign->id));
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testFetchCampaignById(): void
    {
        $user = $this->setUpUser();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);

        $response = $this->get(self::buildUriCampaign($campaign->id));
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CAMPAIGN_STRUCTURE);
    }

    public function testFetchCampaignByIdWhileMissingId(): void
    {
        $this->setUpUser();

        $response = $this->get(self::buildUriCampaign(PHP_INT_MAX));
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFetchCampaignByIdWhileMissingScope(): void
    {
        Passport::actingAs(User::factory()->create(), [], 'jwt');

        $response = $this->get(self::buildUriCampaign(PHP_INT_MAX));
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFetchCampaignByIdWhileOtherUser(): void
    {
        $this->setUpUser();
        $campaign = Campaign::factory()->create();

        $response = $this->get(self::buildUriCampaign($campaign->id));
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFetchCampaigns(): void
    {
        $user = $this->setUpUser();
        Campaign::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->get(self::URI_CAMPAIGNS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CAMPAIGNS_STRUCTURE);
        $response->assertJsonCount(3, 'data');
    }

    private static function buildUriCampaign(int $id): string
    {
        return sprintf('%s/%d', self::URI_CAMPAIGNS, $id);
    }

    private function setUpUser(): User
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [ScopeType::CAMPAIGN_READ], 'jwt');
        return $user;
    }
}
