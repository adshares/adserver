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
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ScopeType;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PDOException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class ApiCampaignsControllerTest extends TestCase
{
    private const URI_CAMPAIGNS = '/api/v2/campaigns';
    private const UUID_PATTERN = '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/';
    private const CREATIVE_DATA_STRUCTURE = [
        'id',
        'createdAt',
        'updatedAt',
        'type',
        'mime',
        'hash',
        'scope',
        'name',
        'status',
        'cdnUrl',
        'url',
    ];
    private const CAMPAIGN_DATA_STRUCTURE = [
        'id',
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
            'id',
            'url',
        ],
    ];

    public function testAddCampaign(): void
    {
        $this->setUpUser();

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
        $response->assertJsonPath('data.maxCpc', 0);
        $response->assertJsonPath('data.maxCpm', null);
        $response->assertJsonPath('data.budget', 10);
        $response->assertJsonPath('data.conversionClick', 'none');
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $response->json('data.id'));
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $response->json('data.creatives.0.id'));
        $campaign = Campaign::first();
        self::assertNotNull($campaign);
        self::assertEquals($campaign->uuid, str_replace('-', '', $this->getIdFromLocationHeader($response)));
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

    public function testAddCampaignDraftWithoutCreatives(): void
    {
        $this->setUpUser();

        $campaignData = self::getCampaignData(['status' => 'draft'], remove: 'creatives');

        $response = $this->post(self::URI_CAMPAIGNS, $campaignData);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    /**
     * @dataProvider addCampaignFailProvider
     */
    public function testAddCampaignFail(Closure $closure): void
    {
        $this->setUpUser();
        $campaignData = $closure();

        $response = $this->post(self::URI_CAMPAIGNS, $campaignData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function addCampaignFailProvider(): array
    {
        return [
            'missing campaign' => [fn() => self::getCampaignData(remove: 'targetUrl')],
            'missing creatives while not draft' => [fn() => self::getCampaignData(remove: 'creatives')],
            'empty creatives while not draft' => [fn() => self::getCampaignData(['creatives' => []])],
            'invalid creatives type' => [fn() => self::getCampaignData(['creatives' => 'no'])],
            'missing creatives[].id' => [
                fn() => self::getCampaignData(['creatives' => self::getBannerData('fileId')])
            ],
        ];
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
            'maxCpc' => 2,
            'maxCpm' => 1,
            'budget' => 100,
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
        $response->assertJsonPath('data.maxCpc', 2);
        $response->assertJsonPath('data.maxCpm', 1);
        $response->assertJsonPath('data.budget', 100);
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

    public function testEditCampaignFailWhileInvalidUid(): void
    {
        $this->setUpUser();
        $uri = sprintf('%s/%d', self::URI_CAMPAIGNS, 1);
        $campaignData = ['name' => 'Edited campaign'];

        $response = $this->patch($uri, $campaignData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddBanner(): void
    {
        $user = $this->setUpUser();
        $this->post(self::URI_CAMPAIGNS, self::getCampaignData());
        $campaign = Campaign::first();
        $campaignId = $campaign->id;
        $file = UploadedFileModel::factory()->create([
            'user_id' => $user,
            'scope' => '980x120',
            'content' => file_get_contents(base_path('tests/mock/Files/Banners/980x120.png')),
        ]);

        $response = $this->post(self::buildUriBanner($campaign), [
            'fileId' => $file->uuid,
            'name' => 'IMAGE 2',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertHeader('Location');
        $response->assertJsonStructure(self::CREATIVE_STRUCTURE);
        $bannerId = $this->getIdFromLocationHeader($response);
        $banner = Banner::fetchBanner(str_replace('-', '', $bannerId));
        self::assertNotNull($banner);
        self::assertEquals($campaignId, $banner->campaign_id);
        self::assertEquals('IMAGE 2', $banner->name);
        self::assertEquals(Banner::STATUS_ACTIVE, $banner->status);
        self::assertEquals('image/png', $banner->creative_mime);
        self::assertEquals('980x120', $banner->creative_size);
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
        $campaign = $this->setupCampaignWithTwoBanners();
        $banner = Banner::first();
        $bannerUri = self::buildUriBanner($campaign, $banner);

        $response = $this->patch($bannerUri, ['status' => 'inactive']);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CREATIVE_STRUCTURE);
        self::assertDatabaseHas(Banner::class, [
            'id' => $banner->id,
            'status' => Banner::STATUS_INACTIVE,
        ]);
    }

    public function testEditBannerStatusFailWhenDeactivatingLastActiveBanner(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();

        $response = $this->patch($bannerUri, ['status' => 'inactive']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteBanner(): void
    {
        $campaign = $this->setupCampaignWithTwoBanners();
        $banner = Banner::first();
        $bannerUri = self::buildUriBanner($campaign, $banner);

        $response = $this->delete($bannerUri);

        $response->assertStatus(Response::HTTP_OK);
        self::assertTrue(Banner::withTrashed()->find($banner->id)->trashed());

        $response = $this->get($bannerUri);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteBannerFailWhileDeletingLastActiveBanner(): void
    {
        $bannerUri = $this->setUpCampaignWithBanner();

        $response = $this->delete($bannerUri);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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

    private function getCampaignData(array $mergeData = [], ?string $remove = null): array
    {
        $data = array_merge([
            'status' => 'active',
            'name' => 'Test campaign',
            'targetUrl' => 'https://exmaple.com/landing',
            'maxCpc' => 0,
            'maxCpm' => null,
            'budget' => 10,
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

        if (null !== $remove) {
            unset($data[$remove]);
        }

        return $data;
    }

    private function getBannerData(?string $remove = null): array
    {
        $file = UploadedFileModel::factory()->create(['user_id' => User::first()]);
        $data = [
            'fileId' => $file->uuid,
            'name' => 'IMAGE 1',
        ];

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

        $response = $this->delete(self::buildUriCampaign($campaign));
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

        $response = $this->delete(self::buildUriCampaign($campaign));
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testFetchCampaignById(): void
    {
        $user = $this->setUpUser();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);

        $response = $this->get(self::buildUriCampaign($campaign));
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CAMPAIGN_STRUCTURE);
    }

    public function testFetchCampaignByIdWhileUnknownId(): void
    {
        $this->setUpUser();

        $response = $this->get(sprintf('%s/%s', self::URI_CAMPAIGNS, Uuid::uuid4()->toString()));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFetchCampaignByIdWhileMissingScope(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [], 'jwt');
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);

        $response = $this->get(self::buildUriCampaign($campaign));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFetchCampaignByIdWhileOtherUser(): void
    {
        $this->setUpUser();
        $campaign = Campaign::factory()->create();

        $response = $this->get(self::buildUriCampaign($campaign));

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

    public function testFetchCampaignsWithFilterByMediumAndVendor(): void
    {
        $user = $this->setUpUser();
        Campaign::factory()
            ->count(3)
            ->state(
                new Sequence(
                    ['medium' => 'web', 'vendor' => null],
                    ['medium' => 'metaverse', 'vendor' => 'decentraland'],
                    ['medium' => 'metaverse', 'vendor' => 'cryptovoxels'],
                )
            )->create(['user_id' => $user]);

        $query = http_build_query(['filter' => ['medium' => 'metaverse', 'vendor' => 'decentraland']]);
        $response = $this->get(sprintf('%s?%s', self::URI_CAMPAIGNS, $query));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CAMPAIGNS_STRUCTURE);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['medium' => 'metaverse', 'vendor' => 'decentraland']);
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
                'type' => 'image',
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::UPLOAD_STRUCTURE);
    }

    private static function buildUriCampaign(Campaign $campaign): string
    {
        return sprintf('%s/%s', self::URI_CAMPAIGNS, Uuid::fromString($campaign->uuid)->toString());
    }

    private static function buildUriBanner(Campaign $campaign, Banner $banner = null): string
    {
        $uri = sprintf('%s/%s/creatives', self::URI_CAMPAIGNS, Uuid::fromString($campaign->uuid)->toString());
        if (null !== $banner) {
            $uri = sprintf('%s/%s', $uri, Uuid::fromString($banner->uuid)->toString());
        }
        return $uri;
    }

    private function getIdFromLocationHeader(TestResponse $response): string
    {
        $response->assertHeader('Location');
        $matches = [];
        preg_match('~/([^/]+)$~', $response->headers->get('Location'), $matches);

        return $matches[1];
    }

    private function setUpCampaign(): string
    {
        $this->setUpUser();
        $this->post(self::URI_CAMPAIGNS, self::getCampaignData());
        return self::buildUriCampaign(Campaign::first());
    }

    private function setupCampaignWithTwoBanners(): Campaign
    {
        $campaign = Campaign::factory()->create([
            'budget' => 50 * 1e11,
            'status' => Campaign::STATUS_ACTIVE,
            'user_id' => $this->setUpUser()->id,
        ]);
        Banner::factory()->count(2)->create(['campaign_id' => $campaign->id]);
        return $campaign;
    }

    private function setUpCampaignWithBanner(): string
    {
        $this->setUpUser();
        $this->post(self::URI_CAMPAIGNS, self::getCampaignData());
        return self::buildUriBanner(Campaign::first(), Banner::first());
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
