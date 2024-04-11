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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Http\Middleware\StatisticsCollectorAccess;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Factory\TaxonomyV2Factory;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class StatisticsGlobalControllerTest extends TestCase
{
    private const DEMAND_BANNERS_SIZES_URI = '/stats/demand/banners/sizes';
    private const DEMAND_BANNERS_TYPES_URI = '/stats/demand/banners/types';
    private const DEMAND_CAMPAIGNS_URI = '/stats/demand/campaigns';
    private const DEMAND_DOMAINS_URI = '/stats/demand/domains';
    private const DEMAND_STATISTICS_URI = '/stats/demand/statistics';
    private const DEMAND_TURNOVER_URI = '/stats/demand/turnover/{from}/{to}';
    private const SERVER_STATISTICS_URI = '/stats/server/';
    private const SUPPLY_DOMAINS_URI = '/stats/supply/domains';
    private const SUPPLY_STATISTICS_URI = '/stats/supply/statistics';
    private const SUPPLY_TURNOVER_URI = '/stats/supply/turnover/{from}/{to}';
    private const SUPPLY_ZONES_URI = '/stats/supply/zones/sizes';

    private const BANNERS_SIZES_STRUCTURE = [
        '*' => [
            'size',
            'medium',
            'vendor',
            'impressions',
            'number',
        ],
    ];
    private const BANNERS_TYPES_STRUCTURE = [
        '*' => [
            'medium',
            'vendor',
            'types',
        ],
    ];
    private const CAMPAIGNS_STRUCTURE = [
        '*' => [
            'name',
            'medium',
            'vendor',
            'impressions',
            'cost',
            'cpm',
            'sizes',
        ],
    ];
    private const DOMAINS_STRUCTURE = [
        '*' => [
            'name',
            'medium',
            'vendor',
            'impressions',
            'clicks',
            'cost',
            'cpm',
            'sizes',
        ],
    ];
    private const STATISTICS_STRUCTURE = [
        '*' => [
            'date',
            'medium',
            'vendor',
            'impressions',
            'clicks',
            'volume',
        ],
    ];
    private const ZONES_STRUCTURE = [
        '*' => [
            'size',
            'medium',
            'vendor',
            'impressions',
            'number',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(StatisticsCollectorAccess::class);
    }

    public function testFetchDemandStatistics(): void
    {
        self::insertView();

        $response = $this->getJson(self::DEMAND_STATISTICS_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::STATISTICS_STRUCTURE);
    }

    public function testFetchDemandDomains(): void
    {
        self::insertView();

        $response = $this->getJson(self::DEMAND_DOMAINS_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::DOMAINS_STRUCTURE);
        $response->assertJsonFragment(['name' => 'example.com']);
    }

    public function testFetchDemandCampaigns(): void
    {
        self::insertView();

        $response = $this->getJson(self::DEMAND_CAMPAIGNS_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::CAMPAIGNS_STRUCTURE);
        $response->assertJsonFragment(['name' => 'adshares.net']);
    }

    public function testFetchDemandBannersSizes(): void
    {
        self::insertView();

        $response = $this->getJson(self::DEMAND_BANNERS_SIZES_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::BANNERS_SIZES_STRUCTURE);
        $response->assertJsonFragment(['number' => 1]);
    }

    public function testFetchDemandBannersTypes(): void
    {
        $expectedTypes = [
            'display',
            'model',
            'pop',
            'smart-link',
        ];
        $taxonomy = TaxonomyV2Factory::fromJson(
            file_get_contents(base_path('tests/mock/targeting_schema_v2_smart_link.json'))
        );
        $configurationRepositoryMock = self::createMock(ConfigurationRepository::class);
        $configurationRepositoryMock->method('fetchTaxonomy')->willReturn($taxonomy);
        $this->app->bind(ConfigurationRepository::class, fn() => $configurationRepositoryMock);
        self::insertView();

        $response = $this->getJson(self::DEMAND_BANNERS_TYPES_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::BANNERS_TYPES_STRUCTURE);
        $types = array_reduce(
            $response->json(),
            fn(array $carry, array $item) => array_values(array_unique(array_merge($carry, $item['types']))),
            [],
        );
        foreach ($expectedTypes as $expectedType) {
            self::assertContains($expectedType, $types);
        }
    }

    public function testFetchDemandTurnover(): void
    {
        self::initTurnoverEntries();
        $uri = str_replace(
            [
                '{from}',
                '{to}',
            ],
            [
                urlencode('2024-04-01T00:00:00+00:00'),
                urlencode('2024-04-30T23:59:59+00:00'),
            ],
            self::DEMAND_TURNOVER_URI,
        );
        $response = $this->getJson($uri);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['expense']);
        $response->assertJsonFragment(['expense' => 50_000_000_000]);
    }

    public function testFetchDemandTurnoverFailWhileDateOutOfRange(): void
    {
        self::initTurnoverEntries();
        $uri = str_replace(
            [
                '{from}',
                '{to}',
            ],
            [
                urlencode('2024-04-01T00:00:00+00:00'),
                urlencode('2024-04-02T23:59:59+00:00'),
            ],
            self::DEMAND_TURNOVER_URI,
        );
        $response = $this->getJson($uri);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFetchDemandTurnoverFailWhileInvalidInput(): void
    {
        self::initTurnoverEntries();
        $uri = str_replace(
            [
                '{from}',
                '{to}',
            ],
            [
                urlencode('2024-04-01'),
                urlencode('2024-04-30'),
            ],
            self::DEMAND_TURNOVER_URI,
        );
        $response = $this->getJson($uri);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchSupplyStatistics(): void
    {
        self::insertCase();

        $response = $this->getJson(self::SUPPLY_STATISTICS_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::STATISTICS_STRUCTURE);
    }

    public function testFetchSupplyDomains(): void
    {
        self::insertCase();

        $response = $this->getJson(self::SUPPLY_DOMAINS_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::DOMAINS_STRUCTURE);
        $response->assertJsonFragment(['name' => 'example.com']);
    }

    public function testFetchSupplyTurnover(): void
    {
        self::initTurnoverEntries();
        $uri = str_replace(
            [
                '{from}',
                '{to}',
            ],
            [
                urlencode('2024-04-01T00:00:00+00:00'),
                urlencode('2024-04-30T23:59:59+00:00'),
            ],
            self::SUPPLY_TURNOVER_URI,
        );
        $response = $this->getJson($uri);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['expense', 'operatorIncome', 'publishersIncome']);
        $response->assertJsonFragment(['expense' => 130_000_000]);
        $response->assertJsonFragment(['operatorIncome' => 22_000_000_000]);
        $response->assertJsonFragment(['publishersIncome' => 40_000_000_000]);
    }

    public function testFetchSupplyTurnoverFailWhileDateOutOfRange(): void
    {
        self::initTurnoverEntries();
        $uri = str_replace(
            [
                '{from}',
                '{to}',
            ],
            [
                urlencode('2024-04-01T00:00:00+00:00'),
                urlencode('2024-04-02T23:59:59+00:00'),
            ],
            self::SUPPLY_TURNOVER_URI,
        );
        $response = $this->getJson($uri);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testFetchSupplyTurnoverFailWhileInvalidInput(): void
    {
        self::initTurnoverEntries();
        $uri = str_replace(
            [
                '{from}',
                '{to}',
            ],
            [
                urlencode('2024-04-01'),
                urlencode('2024-04-30'),
            ],
            self::SUPPLY_TURNOVER_URI,
        );
        $response = $this->getJson($uri);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchSupplyZonesSizes(): void
    {
        self::insertCase();

        $response = $this->getJson(self::SUPPLY_ZONES_URI);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::ZONES_STRUCTURE);
        $response->assertJsonFragment(['number' => 1]);
    }

    public function testFetchServerStatisticsAsFile(): void
    {
        Storage::disk('public')->put('20240101_statistics.csv', 'test');
        $response = $this->get(self::buildServerStatisticsUri('20240101'));

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testFetchServerStatisticsAsFileFailWhileInvalidInput(): void
    {
        $response = $this->get(self::buildServerStatisticsUri('1'));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testFetchServerStatisticsAsFileFailWhileNoFile(): void
    {
        $response = $this->get(self::buildServerStatisticsUri('20100101'));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private static function insertView(): void
    {
        $campaign = Campaign::factory()->create([
            'landing_url' => 'https://adshares.net/c1',
            'status' => Campaign::STATUS_ACTIVE,
        ]);
        $banner = Banner::factory()->create(['campaign_id' => $campaign]);
        DB::insert(
            <<< SQL
INSERT INTO event_logs_hourly (hour_timestamp, advertiser_id, campaign_id, banner_id, domain, cost, cost_payment,
                               clicks, views, clicks_all, views_all, views_unique)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL
            ,
            [
                (new DateTimeImmutable('-30 hours'))->format('Y-m-d H:00:00'),
                hex2bin($banner->campaign->user->uuid),
                hex2bin($banner->campaign->uuid),
                hex2bin($banner->uuid),
                'example.com',
                0,
                0,
                0,
                1,
                0,
                1,
                1,
            ],
        );
    }

    private static function insertCase(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['user_id' => $user]);
        $zone = Zone::factory()->create(['site_id' => $site]);
        DB::insert(
            <<< SQL
INSERT INTO network_case_logs_hourly (hour_timestamp, publisher_id, site_id, zone_id, domain, revenue_case,
                                      revenue_hour, views_all, views, views_unique)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL
            ,
            [
                (new DateTimeImmutable('-30 hours'))->format('Y-m-d H:00:00'),
                hex2bin($zone->site->user->uuid),
                hex2bin($zone->site->uuid),
                hex2bin($zone->uuid),
                'example.com',
                0,
                0,
                1,
                1,
                1,
            ],
        );
    }

    private static function initTurnoverEntries(): void
    {
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 130_000_000,
            'type' => TurnoverEntryType::SspJoiningFeeExpense,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 70_000_000,
            'type' => TurnoverEntryType::SspJoiningFeeRefund,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 110_000_000_000,
            'type' => TurnoverEntryType::DspJoiningFeeIncome,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 30_000_000_000,
            'type' => TurnoverEntryType::DspJoiningFeeAllocation,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 20_000_000_000,
            'type' => TurnoverEntryType::DspExpense,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 23_000_000_000,
            'type' => TurnoverEntryType::SspIncome,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 3_000_000_000,
            'type' => TurnoverEntryType::SspLicenseFee,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 20_000_000_000,
            'type' => TurnoverEntryType::SspOperatorFee,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 21_000_000_000,
            'type' => TurnoverEntryType::SspPublishersIncome,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 19_000_000_000,
            'type' => TurnoverEntryType::SspBoostLocked,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 17_000_000_000,
            'type' => TurnoverEntryType::SspBoostPublishersIncome,
        ]);
        TurnoverEntry::factory()->create([
            'hour_timestamp' => '2024-04-10 23:00:00',
            'amount' => 2_000_000_000,
            'type' => TurnoverEntryType::SspBoostOperatorIncome,
        ]);
    }

    private static function buildServerStatisticsUri(string $id): string
    {
        return self::SERVER_STATISTICS_URI . $id;
    }
}
