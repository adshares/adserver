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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\Advertiser\MySqlStatsRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use Adshares\Tests\Advertiser\Repository\DummyStatsRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class StatsControllerTest extends TestCase
{
    private const ADVERTISER_CHART_URI = '/api/campaigns/stats/chart';
    private const ADVERTISER_STATS_URI = '/api/campaigns/stats/table2';
    private const PUBLISHER_STATS_URI = '/api/sites/stats/table2';

    public function testAdvertiserChartWhenViewTypeAndHourResolutionAndDateEndIsEarlierThanDateStart(): void
    {
        $this->login();

        $dateStart = (new DateTime())->format(DateTimeInterface::ATOM);
        $dateEnd = (new DateTime())->modify('-1 hour')->format(DateTimeInterface::ATOM);
        $url = sprintf('%s/view/hour/%s/%s', self::ADVERTISER_CHART_URI, $dateStart, $dateEnd);
        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param string $type
     *
     * @dataProvider providerDataForAdvertiserChart
     */
    public function testAdvertiserChartWhenViewTypeAndHourResolution(string $type): void
    {
        $repository = new DummyStatsRepository();
        $user = $this->login();

        $dateStart = new DateTime();
        $dateEnd = new DateTime();

        foreach (ChartResolution::cases() as $chartResolution) {
            $url = sprintf(
                '%s/%s/%s/%s/%s',
                self::ADVERTISER_CHART_URI,
                $type,
                $chartResolution->value,
                $dateStart->format(DateTimeInterface::ATOM),
                $dateEnd->format(DateTimeInterface::ATOM)
            );

            $methodNameMapper = [
                StatsRepository::TYPE_CLICK => 'fetchClick',
                StatsRepository::TYPE_CLICK_ALL => 'fetchClickAll',
                StatsRepository::TYPE_CLICK_INVALID_RATE => 'fetchClickInvalidRate',
                StatsRepository::TYPE_VIEW => 'fetchView',
                StatsRepository::TYPE_VIEW_UNIQUE => 'fetchViewUnique',
                StatsRepository::TYPE_VIEW_ALL => 'fetchViewAll',
                StatsRepository::TYPE_VIEW_INVALID_RATE => 'fetchViewInvalidRate',
                StatsRepository::TYPE_CPC => 'fetchCpc',
                StatsRepository::TYPE_CPM => 'fetchCpm',
                StatsRepository::TYPE_SUM => 'fetchSum',
                StatsRepository::TYPE_SUM_BY_PAYMENT => 'fetchSumPayment',
                StatsRepository::TYPE_CTR => 'fetchCtr',
            ];

            $method = $methodNameMapper[$type];

            $response = $this->getJson($url);
            $response->assertStatus(Response::HTTP_OK);
            $response->assertJson($repository->$method($user->uuid, $chartResolution, $dateStart, $dateEnd)->toArray());
        }
    }

    public function testAdvertiserChartViewsWithFilterByMediumAndVendor(): void
    {
        app()->bind(
            StatsRepository::class,
            function () {
                return new MySqlStatsRepository();
            }
        );
        $user = $this->login();
        $campaign1 = Campaign::factory()
            ->create(['medium' => 'web', 'vendor' => null, 'user_id' => $user]);
        $banner1 = Banner::factory()->create(['campaign_id' => $campaign1]);
        $campaign2 = Campaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'decentraland', 'user_id' => $user]);
        $banner2 = Banner::factory()->create(['campaign_id' => $campaign2]);
        $campaign3 = Campaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'cryptovoxels', 'user_id' => $user]);
        $banner3 = Banner::factory()->create(['campaign_id' => $campaign3]);
        $dateEnd = new DateTimeImmutable();
        $dateStart = $dateEnd->modify('-1 day');
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign1, $banner1);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign1);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign2, $banner2);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign2);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign3, $banner3);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign3);
        $query = http_build_query(['filter' => ['medium' => 'metaverse', 'vendor' => 'decentraland']]);
        $url = sprintf(
            '%s/%s/%s/%s/%s?%s',
            self::ADVERTISER_CHART_URI,
            StatsRepository::TYPE_VIEW,
            'year',
            $dateStart->format(DateTimeInterface::ATOM),
            $dateEnd->format(DateTimeInterface::ATOM),
            $query,
        );

        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('0', [$dateStart->format(DateTimeInterface::ATOM), 1]);
    }

    public function testAdvertiserStats(): void
    {
        $repository = new DummyStatsRepository();
        $user = $this->login(User::factory()->create(['email' => DummyStatsRepository::USER_EMAIL]));
        Campaign::factory()->create(['user_id' => $user->id]);

        $dateStart = new DateTime();
        $dateEnd = new DateTime();
        $url = $this->buildAdvertiserStatsUri($dateStart, $dateEnd);

        $response = $this->getJson($url);
        $response->assertStatus(Response::HTTP_OK);

        $response->assertJson([
            'total' => $repository->fetchStatsTotal($user->uuid, $dateStart, $dateEnd)->toArray(),
            'data' => $repository->fetchStats($user->uuid, $dateStart, $dateEnd)->toArray(),
        ]);
    }

    public function testAdvertiserStatsWithTotal(): void
    {
        $url = $this->seedCampaignsWithStats();

        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_OK);
        self::assertEquals(3, $response->json('total.impressions'));
        self::assertCount(3, $response->json('data'));
    }

    public function testAdvertiserStatsWithTotalWhitFilterByMedium(): void
    {
        $query = http_build_query(['filter' => ['medium' => 'metaverse']]);
        $url = sprintf('%s?%s', $this->seedCampaignsWithStats(), $query);

        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_OK);
        self::assertEquals(2, $response->json('total.impressions'));
        self::assertCount(2, $response->json('data'));
    }

    public function testAdvertiserStatsWithTotalWhitFilterByMediumAndVendor(): void
    {
        $query = http_build_query(['filter' => ['medium' => 'metaverse', 'vendor' => 'decentraland']]);
        $url = sprintf('%s?%s', $this->seedCampaignsWithStats(), $query);

        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_OK);
        self::assertEquals(1, $response->json('total.impressions'));
        self::assertCount(1, $response->json('data'));
    }

    public function testAdvertiserStatsWhenUserIsOnlyPublisher(): void
    {
        $user = $this->login(User::factory()->create(['is_advertiser' => 0]));
        Campaign::factory()->create(['user_id' => $user->id]);

        $url = $this->buildAdvertiserStatsUri(new DateTime(), new DateTime());
        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPublisherStatsWhenUserIsOnlyAdvertiser(): void
    {
        $user = $this->login(User::factory()->create(['is_publisher' => 0]));
        Campaign::factory()->create(['user_id' => $user->id]);

        $url = $this->buildPublisherStatsUri(new DateTime(), new DateTime());
        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function providerDataForAdvertiserChart(): array
    {
        return [
            ['view'],
            ['viewUnique'],
            ['viewAll'],
            ['viewInvalidRate'],
            ['click'],
            ['clickAll'],
            ['clickInvalidRate'],
            ['cpc'],
            ['cpm'],
            ['sum'],
            ['ctr'],
        ];
    }

    private function buildAdvertiserStatsUri(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): string
    {
        return sprintf(
            '%s/%s/%s',
            self::ADVERTISER_STATS_URI,
            $dateStart->format(DateTimeInterface::ATOM),
            $dateEnd->format(DateTimeInterface::ATOM)
        );
    }

    private function buildPublisherStatsUri(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): string
    {
        return sprintf(
            '%s/%s/%s',
            self::PUBLISHER_STATS_URI,
            $dateStart->format(DateTimeInterface::ATOM),
            $dateEnd->format(DateTimeInterface::ATOM)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        app()->bind(
            StatsRepository::class,
            function () {
                return new DummyStatsRepository();
            }
        );
    }

    private function seedCampaignsWithStats(): string
    {
        app()->bind(
            StatsRepository::class,
            function () {
                return new MySqlStatsRepository();
            }
        );
        $user = $this->login();
        $campaign1 = Campaign::factory()
            ->create(['medium' => 'web', 'vendor' => null, 'user_id' => $user]);
        $banner1 = Banner::factory()->create(['campaign_id' => $campaign1]);
        $campaign2 = Campaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'decentraland', 'user_id' => $user]);
        $banner2 = Banner::factory()->create(['campaign_id' => $campaign2]);
        $campaign3 = Campaign::factory()
            ->create(['medium' => 'metaverse', 'vendor' => 'cryptovoxels', 'user_id' => $user]);
        $banner3 = Banner::factory()->create(['campaign_id' => $campaign3]);
        $dateEnd = new DateTimeImmutable();
        $dateStart = $dateEnd->modify('-1 day');
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign1, $banner1);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign1);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign2, $banner2);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign2);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign3, $banner3);
        $this->insertView($dateStart->modify('+5 hours'), $user, $campaign3);

        return $this->buildAdvertiserStatsUri($dateStart, $dateEnd);
    }

    private function insertView(
        DateTimeInterface $date,
        User $user,
        Campaign $campaign,
        ?Banner $banner = null,
    ): void {
        DB::insert(
            <<< SQL
      INSERT INTO event_logs_hourly_stats (hour_timestamp, advertiser_id, campaign_id, banner_id, cost, cost_payment,
                                           clicks, views, clicks_all, views_all, views_unique)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      SQL
            ,
            [
                $date->modify('+5 hours')->format('Y-m-d H:00:00'),
                hex2bin($user->uuid),
                hex2bin($campaign->uuid),
                $banner ? hex2bin($banner->uuid) : null,
                0,
                0,
                0,
                1,
                0,
                1,
                1,
            ]
        );
    }
}
