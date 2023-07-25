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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkMissedCase;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class AggregateCaseStatisticsPublisherCommandTest extends ConsoleTestCase
{
    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan('ops:stats:aggregate:publisher')
            ->expectsOutput('Command ops:stats:aggregate:publisher already running')
            ->assertExitCode(0);
    }

    public function testHandle(): void
    {
        $pastDate = new DateTimeImmutable('-1 hour');
        $hour = $pastDate->setTime((int)$pastDate->format('H'), 0)->format('Y-m-d H:i:s');
        /** @var User $publisher */
        $publisher = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $publisher]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);
        /** @var NetworkImpression $impression */
        $impression = NetworkImpression::factory()->create();
        NetworkCase::factory()->create([
            'created_at' => $pastDate,
            'network_impression_id' => $impression->id,
            'publisher_id' => $publisher->uuid,
            'site_id' => $site->uuid,
            'zone_id' => $zone->uuid,
        ]);
        NetworkMissedCase::factory()->create([
            'created_at' => $pastDate,
            'network_impression_id' => $impression->id,
            'publisher_id' => $publisher->uuid,
            'site_id' => $site->uuid,
            'zone_id' => $zone->uuid,
        ]);

        NetworkCase::factory()->create();
        $this->artisan('ops:stats:aggregate:publisher', ['--hour' => $hour])
            ->assertExitCode(0);

        self::assertDatabaseCount(NetworkCaseLogsHourlyMeta::class, 1);
        self::assertDatabaseHas(
            NetworkCaseLogsHourlyMeta::class,
            ['status' => NetworkCaseLogsHourlyMeta::STATUS_VALID],
        );
        $networkCaseLogs = DB::select(
            <<<SQL
SELECT
    hour_timestamp,
    SUM(views_all) AS views_all,
    SUM(views_missed) AS views_missed
FROM network_case_logs_hourly
GROUP BY 1
SQL
        );
        self::assertCount(1, $networkCaseLogs);
        $log = $networkCaseLogs[0];
        self::assertEquals($hour, $log->hour_timestamp);
        self::assertEquals(2, $log->views_all);
        self::assertEquals(1, $log->views_missed);
        $networkCaseLogs = DB::select('SELECT * FROM network_case_logs_hourly_stats WHERE zone_id IS NOT NULL');
        self::assertCount(1, $networkCaseLogs);
        $log = $networkCaseLogs[0];
        self::assertEquals($hour, $log->hour_timestamp);
        self::assertEquals(2, $log->views_all);
        self::assertEquals(1, $log->views_missed);
        $networkCaseLogs = DB::select('SELECT * FROM network_case_logs_hourly_stats WHERE zone_id IS NULL');
        self::assertCount(1, $networkCaseLogs);
        $log = $networkCaseLogs[0];
        self::assertEquals($hour, $log->hour_timestamp);
        self::assertEquals(2, $log->views_all);
        self::assertEquals(1, $log->views_missed);
    }

    public function testHandleFailWhileMissingCases(): void
    {
        $pastDate = new DateTimeImmutable('-2 hour');
        $hour = $pastDate->setTime((int)$pastDate->format('H'), 0);

        $this->artisan(
            'ops:stats:aggregate:publisher',
            [
                '--bulk' => true,
                '--hour' => $hour->format('Y-m-d H:i:s'),
            ]
        )->assertExitCode(0);

        self::assertDatabaseCount(NetworkCaseLogsHourlyMeta::class, 2);
        self::assertDatabaseHas(
            NetworkCaseLogsHourlyMeta::class,
            ['status' => NetworkCaseLogsHourlyMeta::STATUS_ERROR],
        );
    }

    public function testHandleFailWhileInvalidHourFormat(): void
    {
        $this->artisan('ops:stats:aggregate:publisher', ['--hour' => 'invalid'])
            ->expectsOutput('[Aggregate statistics] Invalid hour option format "invalid"')
            ->assertExitCode(0);
    }
}
