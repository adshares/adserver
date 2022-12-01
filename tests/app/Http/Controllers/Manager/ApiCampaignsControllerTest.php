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
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ScopeType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

final class ApiCampaignsControllerTest extends TestCase
{
    private const URI_CAMPAIGNS = '/api/v2/campaigns';
    private const CREATIVE_DATA_STRUCTURE = [
        'id',
        'uuid',
        'createdAt',
        'updatedAt',
        'type',
        'mimeType',
        'hash',
        'size',
        'name',
        'status',
        'cdnUrl',
        'url',
    ];
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
        'targeting' => [
            'requires',
            'excludes',
        ],
        'creatives' => [
            '*' => self::CREATIVE_DATA_STRUCTURE,
        ],
        'bidStrategyUuid',
        'conversions' => [],
    ];
    private const CREATIVE_STRUCTURE = [
        'data' => self::CREATIVE_DATA_STRUCTURE,
    ];
    private const CREATIVES_STRUCTURE = [
        'data' => [
            '*' => self::CREATIVE_DATA_STRUCTURE,
        ],
    ];
    private const CAMPAIGN_STRUCTURE = [
        'data' => self::CAMPAIGN_DATA_STRUCTURE,
    ];
    private const CAMPAIGNS_STRUCTURE = [
        'data' => [
            '*' => self::CAMPAIGN_DATA_STRUCTURE,
        ],
    ];
    private const UPLOAD_STRUCTURE = [
        'data' => [
            'name',
            'size',
            'url',
        ],
    ];

    public function testAddCampaign(): void
    {
        $this->setUpUser();
        $this->mockStorage();

        $campaignData = self::getCampaignData();
        $dateStart = $campaignData['dateStart'];
        $dateEnd = $campaignData['dateEnd'];
        $requires = $campaignData['targeting']['requires'];
        $excludes = $campaignData['targeting']['excludes'];
        $response = $this->post(self::URI_CAMPAIGNS, $campaignData);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');
        $response->assertJsonStructure(self::CAMPAIGN_STRUCTURE);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.creatives.0.status', 'active');
        $response->assertJsonPath('data.conversionClick', 'none');
        $campaign = Campaign::first();
        self::assertNotNull($campaign);
        self::assertEquals($campaign->id, $this->getIdFromLocationHeader($response));
        self::assertEquals(Campaign::STATUS_ACTIVE, $campaign->status);
        self::assertEquals('Test campaign', $campaign->name);
        self::assertEquals('https://exmaple.com/landing', $campaign->landing_url);
        self::assertEquals(0, $campaign->max_cpc);
        self::assertNull($campaign->max_cpm);
        self::assertEquals((int)(10 * 1e11), $campaign->budget);
        self::assertEquals('web', $campaign->medium);
        self::assertNull($campaign->vendor);
        self::assertEquals($dateStart, $campaign->time_start);
        self::assertEquals($dateEnd, $campaign->time_end);
        self::assertEquals($requires, $campaign->targeting_requires);
        self::assertEquals($excludes, $campaign->targeting_excludes);
        $banner = Banner::first();
        self::assertNotNull($banner);
        self::assertEquals($campaign->id, $banner->campaign_id);
        self::assertEquals('IMAGE 1', $banner->name);
        self::assertEquals(Banner::STATUS_ACTIVE, $banner->status);
        self::assertEquals('image/png', $banner->creative_mime);
        self::assertEquals('300x250', $banner->creative_size);
        self::assertEquals(Banner::TEXT_TYPE_IMAGE, $banner->creative_type);
    }

    public function testEditCampaign(): void
    {
        $uri = $this->setUpCampaign();
        $user = User::first();

        /** @var BidStrategy $bidStrategy */
        $bidStrategy = BidStrategy::factory()->create(['user_id' => $user->id]);
        $dateStart = (new DateTimeImmutable('+3 days'))->format(DateTimeInterface::ATOM);
        $requires = [
            'site' => [
                'category' => ['health', 'technology'],
                'quality' => ['high', 'medium'],
            ],
        ];
        $excludes = [
            'site' => [
                'domain' => ['malware.xyz'],
            ],
        ];
        $campaignData = [
            'name' => 'Edited campaign',
            'targetUrl' => 'https://exmaple.com/edited/landing',
            'maxCpc' => (int)(2 * 1e11),
            'maxCpm' => (int)(1e11),
            'budget' => (int)(100 * 1e11),
            'dateStart' => $dateStart,
            'dateEnd' => null,
            'targeting' => [
                'requires' => $requires,
                'excludes' => $excludes,
            ],
            'bidStrategyUuid' => $bidStrategy->uuid,
        ];
        $response = $this->patch($uri, $campaignData);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CAMPAIGN_STRUCTURE);
        $campaign = Campaign::first();
        self::assertEquals(Campaign::STATUS_ACTIVE, $campaign->status);
        self::assertEquals('Edited campaign', $campaign->name);
        self::assertEquals('https://exmaple.com/edited/landing', $campaign->landing_url);
        self::assertEquals((int)(2 * 1e11), $campaign->max_cpc);
        self::assertEquals((int)(1e11), $campaign->max_cpm);
        self::assertEquals((int)(100 * 1e11), $campaign->budget);
        self::assertEquals('web', $campaign->medium);
        self::assertNull($campaign->vendor);
        self::assertEquals($dateStart, $campaign->time_start);
        self::assertNull($campaign->time_end);
        self::assertEquals($requires, $campaign->targeting_requires);
        self::assertEquals($excludes, $campaign->targeting_excludes);
        self::assertEquals($bidStrategy->uuid, $campaign->bid_strategy_uuid);
    }

    public function testAddBanner(): void
    {
        $this->setUpUser();
        $this->mockStorage();
        $campaignId = $this->getIdFromLocationHeader(
            $this->post(self::URI_CAMPAIGNS, self::getCampaignData())
        );

        $response = $this->post(self::buildUriBanner($campaignId), [
            'creativeSize' => '728x90',
            'creativeType' => Banner::TEXT_TYPE_IMAGE,
            'name' => 'IMAGE 2',
            'url' => 'https://example.com/upload-preview/image/nADwGi2vTk236I9yCZEBOP3f3qX0eyeiDuRItKeI.png',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');
        $response->assertJsonStructure(self::CREATIVE_STRUCTURE);
        $bannerId = $this->getIdFromLocationHeader($response);
        $banner = Banner::find($bannerId);
        self::assertNotNull($banner);
        self::assertEquals($campaignId, $banner->campaign_id);
        self::assertEquals('IMAGE 2', $banner->name);
        self::assertEquals(Banner::STATUS_ACTIVE, $banner->status);
        self::assertEquals('image/png', $banner->creative_mime);
        self::assertEquals('728x90', $banner->creative_size);
        self::assertEquals(Banner::TEXT_TYPE_IMAGE, $banner->creative_type);
    }

    public function testEditBannerName(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();

        $response = $this->patch($bannerUri, ['name' => 'new banner name']);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CREATIVE_STRUCTURE);
        self::assertDatabaseHas(Banner::class, ['name' => 'new banner name']);
    }

    public function testEditBannerStatus(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();

        $response = $this->patch($bannerUri, ['status' => 'inactive']);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CREATIVE_STRUCTURE);
        self::assertDatabaseHas(Banner::class, ['status' => Banner::STATUS_INACTIVE]);
    }

    public function testDeleteBanner(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();

        $response = $this->delete($bannerUri);

        $response->assertStatus(Response::HTTP_OK);
        self::assertTrue(Banner::withTrashed()->first()->trashed());

        $response = $this->get($bannerUri);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFetchBannerById(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();

        $response = $this->get($bannerUri);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CREATIVE_STRUCTURE);
    }

    public function testFetchBanners(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();
        $bannersUri = join('/', explode('/', $bannerUri, -1));

        $response = $this->get($bannersUri);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CREATIVES_STRUCTURE);
    }

    private function getCampaignData(array $mergeData = []): array
    {
        return array_merge([
            'status' => 'active',
            'name' => 'Test campaign',
            'targetUrl' => 'https://exmaple.com/landing',
            'maxCpc' => 0,
            'maxCpm' => null,
            'budget' => (int)(10 * 1e11),
            'medium' => 'web',
            'vendor' => null,
            'dateStart' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'dateEnd' => (new DateTimeImmutable('+2 days'))->format(DateTimeInterface::ATOM),
            'targeting' => [
                'requires' => [
                    'site' => [
                        'category' => ['news', 'technology'],
                        'quality' => ['high'],
                    ],
                ],
                'excludes' => [
                    'device' => [
                        'browser' => ['other'],
                    ],
                ],
            ],
            'creatives' => [
                $this->getBannerData(),
            ],
        ], $mergeData);
    }

    private function getBannerData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge(
            [
                'name' => 'IMAGE 1',
                'size' => '300x250',
                'type' => Banner::TEXT_TYPE_IMAGE,
                'url' => 'https://example.com/upload-preview/image/nADwGi2vTk236I9yCZEBOP3f3qX0eyeiDuRItKeI.png',
            ],
            $mergeData,
        );

        if (null !== $remove) {
            unset($data[$remove]);
        }

        return $data;
    }

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
        $response->assertStatus(Response::HTTP_OK);
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

    public function testUpload(): void
    {
        $user = $this->setUpUser();
        Campaign::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->post(
            self::URI_CAMPAIGNS . '/creative',
            [
                'file' => UploadedFile::fake()->image('photo.jpg', 300, 250),
                'medium' => 'web',
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::UPLOAD_STRUCTURE);
        $response->assertJsonPath('data.size', '300x250');
    }

    private static function buildUriCampaign(int $id): string
    {
        return sprintf('%s/%d', self::URI_CAMPAIGNS, $id);
    }

    private static function buildUriBanner(int $campaignId, int $bannerId = null): string
    {
        $uri = sprintf('%s/%d/creatives', self::URI_CAMPAIGNS, $campaignId);
        if (null !== $bannerId) {
            $uri = sprintf('%s/%d', $uri, $bannerId);
        }
        return $uri;
    }

    private function getIdFromLocationHeader(TestResponse $response): string
    {
        $response->assertHeader('Location');
        $matches = [];
        preg_match('~/(\d+)$~', $response->headers->get('Location'), $matches);

        return $matches[1];
    }

    private function mockStorage(): void
    {
        $adPath = base_path('tests/mock/Files/Banners/980x120.png');
        $filesystemMock = self::createMock(FilesystemAdapter::class);
        $filesystemMock->method('exists')->willReturn(function ($fileName) {
            return 'nADwGi2vTk236I9yCZEBOP3f3qX0eyeiDuRItKeI.png' === $fileName;
        });
        $filesystemMock->method('get')->willReturnCallback(function ($fileName) use ($adPath) {
            return 'nADwGi2vTk236I9yCZEBOP3f3qX0eyeiDuRItKeI.png' === $fileName ? file_get_contents($adPath) : null;
        });
        $filesystemMock->method('path')->willReturn($adPath);
        Storage::shouldReceive('disk')->andReturn($filesystemMock);
    }

    private function setUpCampaign(): string
    {
        $this->setUpUser();
        $this->mockStorage();
        $campaignId = $this->getIdFromLocationHeader(
            $this->post(self::URI_CAMPAIGNS, self::getCampaignData())
        );
        return self::buildUriCampaign($campaignId);
    }

    private function setUpCampaignWithBanner(): string
    {
        $this->setUpUser();
        $this->mockStorage();
        $campaignId = $this->getIdFromLocationHeader(
            $this->post(self::URI_CAMPAIGNS, self::getCampaignData())
        );
        $bannerId = Banner::first()->id;
        return self::buildUriBanner($campaignId, $bannerId);
    }

    private function setUpUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        UserLedgerEntry::factory()->create(['user_id' => $user->id, 'amount' => (int)(400 * 1e11)]);
        Passport::actingAs($user, [ScopeType::CAMPAIGN_READ], 'jwt');
        return $user;
    }
}
