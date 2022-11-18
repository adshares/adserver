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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ScopeType;
use Laravel\Passport\Passport;
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
