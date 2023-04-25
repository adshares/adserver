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

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Repository\Publisher\MySqlStatsRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
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
        self::initRepository($publisher);
        $repository = new MySqlStatsRepository();

        $result = $repository->fetchView(
            $publisher->uuid,
            ChartResolution::HOUR,
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

    private static function initRepository(User $publisher): void
    {
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $publisher]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);
        self::insertViews($publisher, $site, $zone, '2018-01-01 22:00:00', 1);
        self::insertViews($publisher, $site, $zone, '2018-01-01 23:00:00', 2);
        self::insertViews($publisher, $site, $zone, '2018-01-02 00:00:00', 3);
        self::insertViews($publisher, $site, $zone, '2018-01-02 01:00:00', 4);
    }

    private static function insertViews(
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
}
