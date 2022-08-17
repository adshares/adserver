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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTime;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class CampaignsControllerTest extends TestCase
{
    private const URI = '/api/campaigns';

    public function testBrowseCampaignRequestWhenNoCampaigns(): void
    {
        $this->createUser();

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);
    }

    public function testCampaignRequestWhenCampaignIsNotFound(): void
    {
        $this->createUser();

        $response = $this->getJson(self::URI . '/1');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testAddCampaignWithBanner(): void
    {
        $adPath = base_path('tests/mock/980x120.png');
        $filesystemMock = self::createMock(FilesystemAdapter::class);
        $filesystemMock->method('get')->willReturn(file_get_contents($adPath));
        $filesystemMock->method('path')->willReturn($adPath);
        Storage::shouldReceive('disk')->andReturn($filesystemMock);
        $this->createUser();

        $response = $this->postJson(self::URI, ['campaign' => $this->getCampaignData()]);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    /**
     * @dataProvider addCampaignWithBannerInvalidProvider
     */
    public function testAddCampaignWithInvalidBanner(array $campaignInputData): void
    {
        $adPath = base_path('tests/mock/980x120.png');
        $filesystemMock = self::createMock(FilesystemAdapter::class);
        $filesystemMock->method('get')->willReturn(file_get_contents($adPath));
        $filesystemMock->method('path')->willReturn($adPath);
        Storage::shouldReceive('disk')->andReturn($filesystemMock);
        $this->createUser();

        $response = $this->postJson(self::URI, ['campaign' => $campaignInputData]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function addCampaignWithBannerInvalidProvider(): array
    {
        return [
            'without size' => [$this->getCampaignData(['ads' => [$this->getBannerData([], 'creativeSize')]])],
            'with empty size' => [$this->getCampaignData(['ads' => [$this->getBannerData(['creativeSize' => ''])]])],
            'with invalid size type' =>
                [$this->getCampaignData(['ads' => [$this->getBannerData(['creativeSize' => 1])]])],
            'without type' => [$this->getCampaignData(['ads' => [$this->getBannerData([], 'creativeType')]])],
            'with empty type' => [$this->getCampaignData(['ads' => [$this->getBannerData(['creativeType' => ''])]])],
            'with invalid type' =>
                [$this->getCampaignData(['ads' => [$this->getBannerData(['creativeType' => 1])]])],
            'without name' => [$this->getCampaignData(['ads' => [$this->getBannerData([], 'name')]])],
            'with invalid name type' => [$this->getCampaignData(['ads' => [$this->getBannerData(['name' => 1])]])],
            'with empty name' => [$this->getCampaignData(['ads' => [$this->getBannerData(['name' => ''])]])],
            'without url' => [$this->getCampaignData(['ads' => [$this->getBannerData([], 'url')]])],
            'with empty url' => [$this->getCampaignData(['ads' => [$this->getBannerData(['url' => ''])]])],
            'with invalid url type' => [$this->getCampaignData(['ads' => [$this->getBannerData(['url' => 1])]])],
            'size not in taxonomy' =>
                [$this->getCampaignData(['ads' => [$this->getBannerData(['creativeSize' => '600x600'])]])],
        ];
    }

    private function getCampaignData(array $mergeData = []): array
    {
        return array_merge(
            $this->campaignInputData(),
            [
                'ads' => [
                    $this->getBannerData(),
                ],
            ],
            $mergeData,
        );
    }

    private function getBannerData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge(
            [
                'creativeSize' => '300x250',
                'creativeType' => 'image',
                'name' => 'IMAGE 1',
                'url' =>
                    'http://localhost:8010/upload-preview/image/nADwGi2vTk236I9yCZEBOP3f3qX0eyeiDuRItKeI.png',
            ],
            $mergeData,
        );

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    /** @dataProvider budgetVsResponseWhenCreatingCampaign */
    public function testCreateCampaignWithoutBannersAndTargeting(int $budget, int $returnValue): void
    {
        $this->createUser();

        $campaignInputData = $this->campaignInputData();
        $campaignInputData['basicInformation']['budget'] = $budget;
        $response = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response->assertStatus($returnValue);

        if ($returnValue === Response::HTTP_CREATED) {
            $id = $this->getIdFromLocation($response->headers->get('Location'));

            $response = $this->getJson(self::URI . '/' . $id);
            $response->assertStatus(Response::HTTP_OK);
        }
    }

    public function testCreateCampaignWithInvalidMedium(): void
    {
        $this->createUser();

        $campaignInputData = $this->campaignInputData();
        $campaignInputData['basicInformation']['medium'] = 'invalid';
        $response = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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
                'medium' => 'web',
                'vendor' => null,
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
        $user = $this->createUser();

        $campaign = $this->createCampaignForUser($user);
        $banner = $this->createBannerForCampaign($campaign);

        $this->assertCount(1, Campaign::where('id', $campaign->id)->get());
        $this->assertCount(1, Banner::where('id', $banner->id)->get());

        $response = $this->deleteJson(self::URI . "/{$campaign->id}");
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertCount(0, Campaign::where('id', $campaign->id)->get());
        $this->assertCount(0, Banner::where('id', $banner->id)->get());
        $this->assertCount(1, Campaign::withTrashed()->where('id', $campaign->id)->get());
        $this->assertCount(1, Banner::withTrashed()->where('id', $banner->id)->get());

        $response = $this->deleteJson(self::URI . "/{$campaign->id}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @dataProvider budgetVsResponseWhenStatusChange */
    public function testCampaignStatusChange(int $campaignBudget, int $expectedResponseCode): void
    {
        $user = $this->createUser();

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'user_id' => $user->id,
            'budget' => $campaignBudget,
        ]);

        $response = $this->putJson(
            self::buildCampaignStatusUri($campaign->id),
            [
                'campaign' => ['status' => Campaign::STATUS_ACTIVE],
            ]
        );

        $response->assertStatus($expectedResponseCode);
    }

    /** @dataProvider campaignStatusChangeBlockadesProvider */
    public function testCampaignStatusChangeBlockades(Currency $currency, int $expectedBlockade): void
    {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);
        $user = $this->createUser();

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'user_id' => $user->id,
            'budget' => 1e11,
        ]);

        $response = $this->putJson(
            self::buildCampaignStatusUri($campaign->id),
            [
                'campaign' => ['status' => Campaign::STATUS_ACTIVE],
            ]
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertDatabaseHas(UserLedgerEntry::class, ['amount' => $expectedBlockade]);
    }

    public function campaignStatusChangeBlockadesProvider(): array
    {
        return [
            'ADS' => [
                Currency::ADS,
                -300_030_003_000,// blockade in clicks, x / 0.3333
            ],
            'USD' => [
                Currency::USD,
                -100_000_000_000,// blockade in currency
            ],
        ];
    }

    public function testCampaignWithConversionStatusChange(): void
    {
        $user = $this->createUser();

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'user_id' => $user->id,
            'budget' => 1e11,
        ]);
        $conversionDefinition = Conversiondefinition::factory()->create(['campaign_id' => $campaign->id]);

        $this->putJson(
            self::buildCampaignStatusUri($campaign->id),
            [
                'campaign' => ['status' => Campaign::STATUS_ACTIVE],
            ]
        );

        $this->assertNotNull(ConversionDefinition::fetchById($conversionDefinition->id), 'Missing conversion');
    }

    /** @dataProvider bannerStatusChangeProvider */
    public function testCampaignBannerStatusChange(
        int $bannerStatusBefore,
        int $bannerStatusSet,
        int $bannerStatusExpected,
        int $expectedResponseStatus
    ): void {
        $user = $this->createUser();
        $campaign = Campaign::factory()->create([
            'user_id' => $user->id,
            'budget' => 10 * 10e9,
        ]);
        $banner = Banner::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => $bannerStatusBefore,
        ]);

        $response = $this->putJson(
            self::URI . "/{$campaign->id}/banner/{$banner->id}/status",
            [
                'banner' => [
                    'status' => $bannerStatusSet,
                ],
            ]
        );

        $response->assertStatus($expectedResponseStatus);
        $this->assertEquals($bannerStatusExpected, Banner::find($banner->id)->status);
    }

    public function testCampaignWithConversionBannerStatusChange(): void
    {
        $user = $this->createUser();
        $campaign = Campaign::factory()->create([
            'user_id' => $user->id,
            'budget' => 10 * 10e9,
        ]);
        $conversionDefinition = Conversiondefinition::factory()->create(['campaign_id' => $campaign->id]);
        $banner = Banner::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => Banner::STATUS_INACTIVE,
        ]);

        $this->putJson(
            self::URI . "/{$campaign->id}/banner/{$banner->id}/status",
            [
                'banner' => [
                    'status' => Banner::STATUS_ACTIVE,
                ],
            ]
        );

        $this->assertNotNull(ConversionDefinition::fetchById($conversionDefinition->id), 'Missing conversion');
    }

    private function createUser(): User
    {
        $userBalance = 5 * 1e11;

        $user = $this->login();
        UserLedgerEntry::factory()->create(['user_id' => $user->id, 'amount' => $userBalance]);

        return $user;
    }

    private function createCampaignForUser(User $user, array $attributes = []): Campaign
    {
        return Campaign::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    private function createBannerForCampaign(Campaign $campaign, array $attributes = []): Banner
    {
        return Banner::factory()->create(array_merge(['campaign_id' => $campaign->id], $attributes));
    }

    public function testFailDeleteNotOwnedCampaign(): void
    {
        $this->createUser();

        $user = User::factory()->create();
        $campaign = $this->createCampaignForUser($user);
        $this->createBannerForCampaign($campaign);

        $response = $this->deleteJson(self::URI . "/{$campaign->id}");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function budgetVsResponseWhenCreatingCampaign(): array
    {
        return [
            'positive budget' => [(int)1e11, Response::HTTP_CREATED],
            'no budget' => [0, Response::HTTP_CREATED],
            'negative budget' => [-11, Response::HTTP_BAD_REQUEST],
        ];
    }

    public function budgetVsResponseWhenStatusChange(): array
    {
        return [
            'insufficient funds' => [(int)(300 * 1e9), Response::HTTP_BAD_REQUEST],
            'sufficient funds' => [(int)(10 * 1e9), Response::HTTP_NO_CONTENT],
        ];
    }

    public function bannerStatusChangeProvider(): array
    {
        $nonExistentBannerStatus = 10;

        return [
            [Banner::STATUS_DRAFT, Banner::STATUS_ACTIVE, Banner::STATUS_ACTIVE, Response::HTTP_NO_CONTENT],
            [Banner::STATUS_ACTIVE, Banner::STATUS_ACTIVE, Banner::STATUS_ACTIVE, Response::HTTP_NO_CONTENT],
            [Banner::STATUS_ACTIVE, Banner::STATUS_INACTIVE, Banner::STATUS_INACTIVE, Response::HTTP_NO_CONTENT],
            [Banner::STATUS_INACTIVE, Banner::STATUS_ACTIVE, Banner::STATUS_ACTIVE, Response::HTTP_NO_CONTENT],
            [Banner::STATUS_ACTIVE, $nonExistentBannerStatus, Banner::STATUS_INACTIVE, Response::HTTP_NO_CONTENT],
            [Banner::STATUS_REJECTED, Banner::STATUS_ACTIVE, Banner::STATUS_REJECTED, Response::HTTP_BAD_REQUEST],
            [Banner::STATUS_REJECTED, Banner::STATUS_INACTIVE, Banner::STATUS_REJECTED, Response::HTTP_BAD_REQUEST],
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

        $user = $this->login();
        foreach ($entries as $entry) {
            UserLedgerEntry::factory()->create([
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

        $campaignInputData = $this->campaignInputData();
        $campaignInputData['basicInformation']['budget'] = $budget;
        $campaignInputData['basicInformation']['dateEnd'] = null;
        if ($hasDomainTargeting) {
            $campaignInputData['targeting']['requires']['site']['domain'] = ['www.adshares.net'];
        }

        $response1 = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response1->assertStatus(Response::HTTP_CREATED);
        $id = $this->getIdFromLocation($response1->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['campaign' => ['basicInformation' => ['status' => $status]]]);
    }

    public function blockingTestProvider(): array
    {
        // campaignBudget,isDirectDeal,ads,bonus,expectedCampaignStatus
        return [
            'not direct deal, has only crypto' => [(int)1e11, false, (int)1e11, 0, Campaign::STATUS_ACTIVE],
            'not direct deal, has only bonus' => [(int)1e11, false, 0, (int)1e11, Campaign::STATUS_ACTIVE],
            'direct deal, has only crypto' => [(int)1e11, true, (int)1e11, 0, Campaign::STATUS_ACTIVE],
            'direct deal, has only bonus' => [(int)1e11, true, 0, (int)1e11, Campaign::STATUS_DRAFT],
        ];
    }

    public function testUpdateBidStrategyValid(): void
    {
        $user = $this->createUser();
        $defaultBidStrategyUuid = BidStrategy::first()->uuid;

        $campaignInputData = $this->campaignInputData();
        $response = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response->assertStatus(Response::HTTP_CREATED);

        $id = $this->getIdFromLocation($response->headers->get('Location'));
        $previousBidStrategyUuid = Campaign::find($id)->bid_strategy_uuid;
        self::assertEquals($defaultBidStrategyUuid, $previousBidStrategyUuid);

        /** @var BidStrategy $bidStrategy */
        $bidStrategy = BidStrategy::factory()->create(['user_id' => $user->id]);
        $campaignInputDataUpdated = array_merge(
            $campaignInputData,
            [
                'bid_strategy' => [
                    'name' => $bidStrategy->name,
                    'uuid' => $bidStrategy->uuid,
                ],
            ]
        );
        $response = $this->patchJson(self::URI . '/' . $id, ['campaign' => $campaignInputDataUpdated]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $currentBidStrategyUuid = Campaign::find($id)->bid_strategy_uuid;
        self::assertNotEquals($previousBidStrategyUuid, $currentBidStrategyUuid);
    }

    /**
     * @dataProvider updateBidStrategyInvalidProvider
     *
     * @param array $bidStrategyData
     */
    public function testUpdateBidStrategyInvalid(array $bidStrategyData): void
    {
        $this->login();

        $campaignInputData = $this->campaignInputData();
        $response = $this->postJson(self::URI, ['campaign' => $campaignInputData]);
        $response->assertStatus(Response::HTTP_CREATED);

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->patchJson(
            self::URI . '/' . $id,
            ['campaign' => array_merge($campaignInputData, $bidStrategyData)]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function updateBidStrategyInvalidProvider(): array
    {
        return [
            'null' => [['bid_strategy' => null]],
            'invalid type' => [['bid_strategy' => 1]],
            'empty' => [['bid_strategy' => []]],
            'no uuid' => [['bid_strategy' => ['name' => 'bid1']]],
            'invalid uuid type' => [['bid_strategy' => ['name' => 'bid1', 'uuid' => 1]]],
            'invalid uuid' => [['bid_strategy' => ['name' => 'bid1', 'uuid' => '0123456789abcdef']]],
            'not existing uuid' => [
                ['bid_strategy' => ['name' => 'bid1', 'uuid' => '0123456789abcdef0123456789abcdef']],
            ],
        ];
    }

    public function testAddCampaignExchangeRateNotAvailable(): void
    {
        $this->app->bind(
            ExchangeRateRepository::class,
            function () {
                $mock = self::createMock(ExchangeRateRepository::class);
                $mock->method('fetchExchangeRate')
                    ->willThrowException(new ExchangeRateNotAvailableException('test'));
                return $mock;
            }
        );
        $this->login();

        $response = $this->postJson(self::URI, ['campaign' => $this->campaignInputData()]);
        $response->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function testAddCampaignInvalidSetupMissingDefaultBidStrategy(): void
    {
        $this->login();

        DB::delete('DELETE FROM bid_strategy WHERE 1=1');

        $response = $this->postJson(self::URI, ['campaign' => $this->campaignInputData()]);
        $response->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function testCloneNoneExistsCampaign(): void
    {
        $user = $this->login();

        $campaign1 = $this->createCampaignForUser($user);
        $campaign2 = $this->createCampaignForUser(User::factory()->create());

        $invalidId = $campaign1->id - 1;
        $response = $this->postJson(self::URI . "/{$invalidId}/clone");
        $response->assertStatus(Response::HTTP_NOT_FOUND);

        $response = $this->postJson(self::URI . "/{$campaign2->id}/clone");
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testCloneEmptyCampaign(): void
    {
        $user = $this->login();

        $campaign = $this->createCampaignForUser(
            $user,
            [
                'status' => Campaign::STATUS_ACTIVE,
                'targeting_requires' => ['foo_tag_1'],
                'targeting_excludes' => ['foo_tag_2'],
            ]
        );

        $response = $this->postJson(self::URI . "/{$campaign->id}/clone");
        $response->assertStatus(Response::HTTP_CREATED);

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(Response::HTTP_OK);

        $cloned = $response->json('campaign');

        $this->assertNotEquals($campaign->id, $cloned['id']);
        $this->assertNotEquals($campaign->uuid, $cloned['uuid']);
        $this->assertNotEquals($campaign->secret, $cloned['secret']);

        $this->assertEquals($campaign->conversion_click, $cloned['conversionClick']);
        $this->assertEquals($campaign->targeting, $cloned['targeting']);
        $this->assertEquals($campaign->bid_strategy_uuid, $cloned['bidStrategyUuid']);

        $info = $cloned['basicInformation'];
        $this->assertEquals(Campaign::STATUS_DRAFT, $info['status']);
        $this->assertNotEquals($campaign->name, $info['name']);
        $this->assertStringContainsString($campaign->name, $info['name']);
        $this->assertEquals($campaign->landing_url, $info['targetUrl']);
        $this->assertEquals($campaign->max_cpc, $info['maxCpc']);
        $this->assertEquals($campaign->max_cpm, $info['maxCpm']);
        $this->assertEquals($campaign->budget, $info['budget']);
        $this->assertEquals($campaign->medium, $info['medium']);
        $this->assertEquals($campaign->vendor, $info['vendor']);
        $this->assertEquals($campaign->time_start, $info['dateStart']);
        $this->assertEquals($campaign->time_end, $info['dateEnd']);

//        $cloned['classificationStatus']
//        $cloned['classificationTags']

        $this->assertEmpty($cloned['conversions']);
        $this->assertEmpty($cloned['classifications']);
        $this->assertEmpty($cloned['ads']);
    }

    public function testCloneCampaignWithConversions(): void
    {
        $user = $this->createUser();

        $campaign = $this->createCampaignForUser($user);
        /** @var ConversionDefinition $conversion */
        $conversion = Conversiondefinition::factory()->create(
            [
                'campaign_id' => $campaign->id,
                'cost' => 1000,
                'occurrences' => 100,
            ]
        );

        $response = $this->postJson(self::URI . "/{$campaign->id}/clone");
        $response->assertStatus(Response::HTTP_CREATED);

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(Response::HTTP_OK);

        $cloned = $response->json('campaign');

        $this->assertNotEmpty($cloned['conversions']);
        $cloned = reset($cloned['conversions']);

        $this->assertNotEquals($conversion->uuid, $cloned['uuid']);
        $this->assertNotEquals($conversion->link, $cloned['link']);
        $this->assertNotEquals($conversion->cost, $cloned['cost']);
        $this->assertNotEquals($conversion->occurrences, $cloned['occurrences']);

        $this->assertEquals($conversion->name, $cloned['name']);
        $this->assertEquals($conversion->limit_type, $cloned['limitType']);
        $this->assertEquals($conversion->event_type, $cloned['eventType']);
        $this->assertEquals($conversion->type, $cloned['type']);
        $this->assertEquals($conversion->value, $cloned['value']);
        $this->assertEquals($conversion->is_value_mutable, $cloned['isValueMutable']);
        $this->assertEquals($conversion->is_repeatable, $cloned['isRepeatable']);
    }

    public function testCloneCampaignWithAds(): void
    {
        $user = $this->createUser();

        $campaign = $this->createCampaignForUser($user);
        $banner = $this->createBannerForCampaign(
            $campaign,
            [
                'cdn_url' => 'http://foo.com'
            ]
        );

        $response = $this->postJson(self::URI . "/{$campaign->id}/clone");
        $response->assertStatus(Response::HTTP_CREATED);

        $id = $this->getIdFromLocation($response->headers->get('Location'));

        $response = $this->getJson(self::URI . '/' . $id);
        $response->assertStatus(Response::HTTP_OK);

        $cloned = $response->json('campaign');

        $this->assertNotEmpty($cloned['ads']);
        $cloned = reset($cloned['ads']);

        $this->assertNotEquals($banner->uuid, $cloned['uuid']);
        $this->assertNotEquals($banner->url, $cloned['url']);

        $this->assertEquals($banner->creative_type, $cloned['creativeType']);
        $this->assertEquals($banner->creative_size, $cloned['creativeSize']);
        $this->assertEquals($banner->creative_sha1, $cloned['creativeSha1']);
        $this->assertEquals($banner->name, $cloned['name']);
        $this->assertEquals($banner->status, $cloned['status']);
        $this->assertEquals($banner->cdn_url, $cloned['cdnUrl']);
    }

    public function testUploadBannerNoFile(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/campaigns/banner');
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadBannerNoMedium(): void
    {
        $this->createUser();

        $response = $this->postJson(
            '/api/campaigns/banner',
            [
                'file' => UploadedFile::fake()->image('photo.jpg', 300, 250),
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadBannerInvalidVendor(): void
    {
        $this->createUser();

        $response = $this->postJson(
            '/api/campaigns/banner',
            [
                'file' => UploadedFile::fake()->image('photo.jpg', 300, 250),
                'medium' => 'web',
                'vendor' => 'premium',
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadBannerInvalidVendorType(): void
    {
        $this->createUser();

        $response = $this->postJson(
            '/api/campaigns/banner',
            [
                'file' => UploadedFile::fake()->image('photo.jpg', 300, 250),
                'medium' => 'web',
                'vendor' => 1,
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUploadBanner(): void
    {
        $this->createUser();

        $response = $this->postJson(
            '/api/campaigns/banner',
            [
                'file' => UploadedFile::fake()->image('photo.jpg', 300, 250),
                'medium' => 'web',
            ]
        );
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testCampaignEditMedium(): void
    {
        $user = $this->login();
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'medium' => 'web',
                'user_id' => $user->id,
            ]
        );

        $response = $this->patchJson(
            self::URI . '/' . $campaign->id,
            ['campaign' => ['basic_information' => ['medium' => 'invalid']]]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function buildCampaignStatusUri(int $campaignId): string
    {
        return sprintf('%s/%d/status', self::URI, $campaignId);
    }
}
