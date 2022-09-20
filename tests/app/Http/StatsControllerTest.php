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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Tests\Advertiser\Repository\DummyStatsRepository;
use DateTime;
use DateTimeInterface;
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
     * @param array $resolutions
     *
     * @dataProvider providerDataForAdvertiserChart
     */
    public function testAdvertiserChartWhenViewTypeAndHourResolution(string $type, array $resolutions): void
    {
        $repository = new DummyStatsRepository();
        $user = $this->login();

        $dateStart = new DateTime();
        $dateEnd = new DateTime();

        foreach ($resolutions as $resolution) {
            $url = sprintf(
                '%s/%s/%s/%s/%s',
                self::ADVERTISER_CHART_URI,
                $type,
                $resolution,
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
            $response->assertJson($repository->$method($user->uuid, $resolution, $dateStart, $dateEnd)->toArray());
        }
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
            ['view', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['viewUnique', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['viewAll', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['viewInvalidRate', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['click', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['clickAll', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['clickInvalidRate', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['cpc', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['cpm', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['sum', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['ctr', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
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
}
