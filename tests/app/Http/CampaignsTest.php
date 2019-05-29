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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function factory;

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

        $response = $this->getJson(self::URI.'/1');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @dataProvider budgetVsResponseWhenCreatingCampaign */
    public function testCreateCampaignWithoutBannersAndTargeting(int $budget, int $returnValue): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $campaignInputData = $this->campaignInputData();
        $campaignInputData['basicInformation']['budget'] = $budget;
        $response = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response->assertStatus($returnValue);

        if ($returnValue === Response::HTTP_CREATED) {
            $id = $this->getIdFromLocation($response->headers->get('Location'));

            $response = $this->getJson(self::URI.'/'.$id);
            $response->assertStatus(Response::HTTP_OK);
        }
    }

    private function campaignInputData(): array
    {
        return [
            'basicInformation' => [
                'status' => Campaign::STATUS_ACTIVE,
                'name' => 'Adshares test campaign',
                'targetUrl' => 'http://adshares.net',
                'max_cpc' => 200000000000,
                'max_cpm' => 100000000000,
                'budget' => 10000000000000,
                'dateStart' => '2018-12-03T18:42:00+01:00',
                'dateEnd' => '2018-12-30T18:42:00+01:00',
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

    public function testDeleteCampaignWithBanner(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $campaignId = $this->createCampaignForUser($user);
        $bannerId = $this->createBannerForCampaign($campaignId);

        $this->assertCount(1, Campaign::where('id', $campaignId)->get());
        $this->assertCount(1, Banner::where('id', $bannerId)->get());

        $response = $this->deleteJson(self::URI."/{$campaignId}");
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertCount(0, Campaign::where('id', $campaignId)->get());
        $this->assertCount(0, Banner::where('id', $bannerId)->get());
        $this->assertCount(1, Campaign::withTrashed()->where('id', $campaignId)->get());
        $this->assertCount(1, Banner::withTrashed()->where('id', $bannerId)->get());

        $response = $this->deleteJson(self::URI."/{$campaignId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @dataProvider budgetVsResponseWhenStatusChange */
    public function testCampaignStatusChange(int $budget, int $responseCode): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $campaign = factory(Campaign::class)->create([
            'user_id' => $user->id,
            'budget' => $budget,
        ]);

        $this->assertCount(1, Campaign::where('id', $campaign->id)->get());

        $response = $this->putJson(
            self::URI."/{$campaign->id}/status",
            [
                'campaign' => ['status' => Campaign::STATUS_ACTIVE],
            ]
        );

        $response->assertStatus($responseCode);
    }

    private function createCampaignForUser(User $user): int
    {
        return factory(Campaign::class)->create(['user_id' => $user->id])->id;
    }

    private function createBannerForCampaign(int $campaignId): int
    {
        return factory(Banner::class)->create(['campaign_id' => $campaignId])->id;
    }

    public function testFailDeleteNotOwnedCampaign(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $user = factory(User::class)->create();
        $campaignId = $this->createCampaignForUser($user);
        $this->createBannerForCampaign($campaignId);

        $response = $this->deleteJson(self::URI."/{$campaignId}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function budgetVsResponseWhenCreatingCampaign(): array
    {
        return [
            [100, Response::HTTP_CREATED],
            [0, Response::HTTP_CREATED],
            [-11, Response::HTTP_BAD_REQUEST],
        ];
    }

    public function budgetVsResponseWhenStatusChange(): array
    {
        return [
            [100, Response::HTTP_BAD_REQUEST],
            [0, Response::HTTP_NO_CONTENT],
        ];
    }

    /** @dataProvider blockingTestProvider */
    public function testAddCampaignWhenNoFunds(
        int $budget,
        bool $hasDomainTargeting,
        int $currency,
        int $bonus,
        int $status
    ): void {
        $entries = [
            [UserLedgerEntry::TYPE_DEPOSIT, $currency, UserLedgerEntry::STATUS_ACCEPTED],
            [UserLedgerEntry::TYPE_BONUS_INCOME, $bonus, UserLedgerEntry::STATUS_ACCEPTED],
        ];

        /** @var User $user */
        $user = factory(User::class)->create();
        foreach ($entries as $entry) {
            factory(UserLedgerEntry::class)->create([
                'type' => $entry[0],
                'amount' => $entry[1],
                'status' => $entry[2],
                'user_id' => $user->id,
            ]);
        }

        $this->app->bind(
            ExchangeRateReader::class,
            function () {
                $mock = $this->createMock(ExchangeRateReader::class);

                $mock->method('fetchExchangeRate')
                    ->willReturn(new ExchangeRate(new DateTime(), 1, 'XXX'));

                return $mock;
            }
        );

        $this->actingAs($user, 'api');

        $campaignInputData = $this->campaignInputData();
        $campaignInputData['basicInformation']['budget'] = $budget;
        if ($hasDomainTargeting) {
            $campaignInputData['targeting']['requires']['site']['domain'] = 'www.adshares.net';
        }

        $response1 = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response1->assertStatus(Response::HTTP_CREATED);
        $id = $this->getIdFromLocation($response1->headers->get('Location'));

        $response = $this->getJson(self::URI.'/'.$id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['campaign' => ['basicInformation' => ['status' => $status]]]);
    }

    public function blockingTestProvider(): array
    {
        return [
            [100, false, 100, 0, Campaign::STATUS_ACTIVE],
            [100, false, 0, 100, Campaign::STATUS_ACTIVE],
            [100, true, 100, 0, Campaign::STATUS_ACTIVE],
            [100, true, 0, 100, Campaign::STATUS_SUSPENDED],
        ];
    }
}
