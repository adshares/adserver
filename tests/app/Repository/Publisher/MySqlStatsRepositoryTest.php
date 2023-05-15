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

namespace Adshares\Adserver\Tests\Repository\Publisher;

use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Repository\Publisher\MySqlStatsRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class MySqlStatsRepositoryTest extends TestCase
{
    private const SQL_INSERT_NETWORK_CASE_LOGS_HOURLY_STATS = <<<SQL
INSERT INTO
    network_case_logs_hourly_stats
    (
        hour_timestamp,
        publisher_id,
        site_id,
        zone_id,
        revenue_case,
        revenue_hour,
        views_all,
        views,
        views_unique,
        clicks_all,
        clicks
    )
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
SQL;

    public function testFetchViewByHour(): void
    {
        /** @var User $publisher */
        $publisher = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $publisher]);
        self::initRepository($publisher, $site);
        $repository = new MySqlStatsRepository();

        $result = $repository->fetchView(
            $publisher->uuid,
            ChartResolution::HOUR,
            new DateTime('2018-01-01 23:00:00'),
            new DateTime('2018-01-02 00:59:59'),
            $site->uuid,
        );

        $resultArray = $result->toArray();
        self::assertCount(2, $resultArray);
        self::assertEquals('2018-01-01T23:00:00+00:00', $resultArray[0][0]);
        self::assertEquals(2, $resultArray[0][1]);
        self::assertEquals('2018-01-02T00:00:00+00:00', $resultArray[1][0]);
        self::assertEquals(3, $resultArray[1][1]);
    }

    public function testFetchViewByDay(): void
    {
        /** @var User $publisher */
        $publisher = User::factory()->create();
        self::initRepository($publisher);
        $repository = new MySqlStatsRepository();

        $result = $repository->fetchView(
            $publisher->uuid,
            ChartResolution::DAY,
            new DateTime('2018-01-01 23:00:00'),
            new DateTime('2018-01-02 00:59:59'),
        );

        $resultArray = $result->toArray();
        self::assertCount(2, $resultArray);
        self::assertEquals('2018-01-01T23:00:00+00:00', $resultArray[0][0]);
        self::assertEquals(2, $resultArray[0][1]);
        self::assertEquals('2018-01-02T00:00:00+00:00', $resultArray[1][0]);
        self::assertEquals(3, $resultArray[1][1]);
    }

    public function testFetchViewLive(): void
    {
        /** @var User $publisher */
        $publisher = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $publisher]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);
        $this->insertViewsLive($publisher, $site, $zone);
        $repository = new MySqlStatsRepository();
        $dateStart = new DateTime('-10 minutes');
        $dateStart->setTime((int)$dateStart->format('H'), 0);

        $result = $repository->fetchView(
            $publisher->uuid,
            ChartResolution::HOUR,
            $dateStart,
            new DateTime(),
            $site->uuid,
        );

        $resultArray = $result->toArray();
        self::assertNotEmpty($resultArray);
        $size = count($resultArray);
        // if test is executed 0-10 minutes after full hour, two records should be returned
        self::assertLessThanOrEqual(2, $size);
        self::assertEquals($dateStart->format(DateTimeInterface::ATOM), $resultArray[0][0]);
        $lastIndex = $size - 1;
        self::assertEquals(1, $resultArray[$lastIndex][1]);
    }

    /**
     * @dataProvider fetchEmptyRepositoryProvider
     */
    public function testFetchEmptyRepository(string $method): void
    {
        $repository = new MySqlStatsRepository();

        $result = $repository->$method(
            '10000000000000000000000000000000',
            ChartResolution::HOUR,
            new DateTime('2023-04-26 09:00:00'),
            new DateTime('2023-04-26 09:59:59'),
        );

        $resultArray = $result->toArray();
        self::assertCount(1, $resultArray);
        self::assertEquals('2023-04-26T09:00:00+00:00', $resultArray[0][0]);
        self::assertEquals(0, $resultArray[0][1]);
    }

    public function fetchEmptyRepositoryProvider(): array
    {
        return [
            'fetchClick' => ['fetchClick'],
            'fetchClickAll' => ['fetchClickAll'],
            'fetchClickInvalidRate' => ['fetchClickInvalidRate'],
            'fetchView' => ['fetchView'],
            'fetchViewUnique' => ['fetchViewUnique'],
            'fetchViewAll' => ['fetchViewAll'],
            'fetchViewInvalidRate' => ['fetchViewInvalidRate'],
            'fetchRpc' => ['fetchRpc'],
            'fetchRpm' => ['fetchRpm'],
            'fetchSum' => ['fetchSum'],
            'fetchSumPayment' => ['fetchSumHour'],
            'fetchCtr' => ['fetchCtr'],
        ];
    }

    private static function initRepository(User $publisher, ?Site $site = null): void
    {
        if (null === $site) {
            /** @var Site $site */
            $site = Site::factory()->create(['user_id' => $publisher]);
        }
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);
        self::insertViewsAggregates($publisher, $site, $zone, '2018-01-01 22:00:00', 1);
        self::insertViewsAggregates($publisher, $site, $zone, '2018-01-01 23:00:00', 2);
        self::insertViewsAggregates($publisher, $site, $zone, '2018-01-02 00:00:00', 3);
        self::insertViewsAggregates($publisher, $site, $zone, '2018-01-02 01:00:00', 4);
    }

    private static function insertViewsAggregates(
        User $publisher,
        Site $site,
        Zone $zone,
        string $hourTimestamp,
        int $views,
    ): void {
        DB::insert(
            self::SQL_INSERT_NETWORK_CASE_LOGS_HOURLY_STATS,
            [
                $hourTimestamp,
                hex2bin($publisher->uuid),
                hex2bin($site->uuid),
                hex2bin($zone->uuid),
                0,
                0,
                $views,
                $views,
                $views,
                0,
                0,
            ]
        );
        DB::insert(
            self::SQL_INSERT_NETWORK_CASE_LOGS_HOURLY_STATS,
            [
                $hourTimestamp,
                hex2bin($publisher->uuid),
                hex2bin($site->uuid),
                null,
                0,
                0,
                $views,
                $views,
                $views,
                0,
                0,
            ]
        );
    }

    private function insertViewsLive(
        User $publisher,
        Site $site,
        Zone $zone,
    ): void {
        NetworkCase::factory()->create(
            [
                'network_impression_id' => NetworkImpression::factory()->create(),
                'publisher_id' => $publisher->uuid,
                'site_id' => $site->uuid,
                'zone_id' => $zone->uuid,
            ]
        );
    }
}
