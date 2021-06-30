<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\DB;

use function factory;

class SiteRankReassessRequestCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:supply:site-rank:reassess';

    public function testEmpty(): void
    {
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testManyViews(): void
    {
        self::insertStatsForTooManyViews();

        $adUser = $this->createMock(AdUser::class);
        $expected = [
            [
                'url' => 'https://example.com',
                'extra' => [
                    [
                        'reason' => 'many views',
                        'message' => 'Views: 100,000',
                    ],
                ],
            ],
        ];
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->with(self::equalTo($expected))
            ->willReturn([['status' => AdUser::REASSESSMENT_STATE_NOT_REGISTERED]]);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testHighCtr(): void
    {
        /** @var Site $site */
        $site = factory(Site::class)->create(['reassess_available_at' => new DateTimeImmutable('-1 minute')]);
        $views = 600;
        $clicks = $views / 2;

        DB::insert(
            'INSERT INTO network_case_logs_hourly_stats (publisher_id,site_id,views_all,views,clicks_all,clicks) '
            . 'VALUES (0x00000000000000000000000000000001,?,?,?,?,?)',
            [hex2bin($site->uuid), $views, $views, $clicks, $clicks]
        );

        $adUser = $this->createMock(AdUser::class);
        $expected = [
            [
                'url' => 'https://example.com',
                'extra' => [
                    [
                        'reason' => 'high ctr',
                        'message' => 'CTR: 50.00% (for 600 views)',
                    ],
                ],
            ],
        ];
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->with(self::equalTo($expected))
            ->willReturn([['status' => AdUser::REASSESSMENT_STATE_NOT_REGISTERED]]);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testTopRevenue(): void
    {
        /** @var Site $site */
        $site = factory(Site::class)->create(['reassess_available_at' => new DateTimeImmutable('-1 minute')]);
        $views = 1;
        $revenue = 1e9;

        DB::insert(
            'INSERT INTO network_case_logs_hourly_stats (publisher_id,site_id,views_all,views,revenue_case) '
            . 'VALUES (0x00000000000000000000000000000001,?,?,?,?)',
            [hex2bin($site->uuid), $views, $views, $revenue]
        );

        $adUser = $this->createMock(AdUser::class);
        $expected = [
            [
                'url' => 'https://example.com',
                'extra' => [
                    [
                        'reason' => 'top revenue',
                        'message' => 'TOP 30 revenue: $0.01',
                    ],
                ],
            ],
        ];
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->with(self::equalTo($expected))
            ->willReturn([['status' => AdUser::REASSESSMENT_STATE_NOT_REGISTERED]]);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testMultiReason(): void
    {
        /** @var Site $site */
        $site = factory(Site::class)->create(['reassess_available_at' => new DateTimeImmutable('-1 minute')]);
        $views = 100000;
        $clicks = $views / 2;
        $revenue = 1e10;

        DB::insert(
            'INSERT INTO network_case_logs_hourly_stats '
            . '(publisher_id,site_id,views_all,views,clicks_all,clicks,revenue_case) '
            . 'VALUES (0x00000000000000000000000000000001,?,?,?,?,?,?)',
            [hex2bin($site->uuid), $views, $views, $clicks, $clicks, $revenue]
        );

        $adUser = $this->createMock(AdUser::class);
        $expected = [
            [
                'url' => 'https://example.com',
                'extra' => [
                    [
                        'reason' => 'many views',
                        'message' => 'Views: 100,000',
                    ],
                    [
                        'reason' => 'high ctr',
                        'message' => 'CTR: 50.00% (for 100,000 views)',
                    ],
                    [
                        'reason' => 'top revenue',
                        'message' => 'TOP 30 revenue: $0.10',
                    ],
                ],
            ],
        ];
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->with(self::equalTo($expected))
            ->willReturn([['status' => AdUser::REASSESSMENT_STATE_NOT_REGISTERED]]);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    /**
     * @dataProvider adUserExceptionProvider
     *
     * @param Exception $exception
     */
    public function testAdUserException(Exception $exception): void
    {
        self::insertStatsForTooManyViews();

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->willThrowException($exception);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function adUserExceptionProvider(): array
    {
        return [
            'unexpected response' => [new UnexpectedClientResponseException('test-exception')],
            'runtime' => [new RuntimeException('test-exception')],
        ];
    }

    /**
     * @dataProvider adUserResponseProvider
     *
     * @param array $response
     * @param bool $reassessmentUpdated
     */
    public function testAdUserResponse(array $response, bool $reassessmentUpdated = false): void
    {
        self::insertStatsForTooManyViews();
        $initialDate = Site::first()->reassess_available_at;

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->willReturn($response);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        if ($reassessmentUpdated) {
            self::assertGreaterThan($initialDate, Site::first()->reassess_available_at);
        } else {
            self::assertEquals($initialDate, Site::first()->reassess_available_at);
        }
    }

    public function adUserResponseProvider(): array
    {
        return [
            'invalid index' => [['100' => ['status' => AdUser::REASSESSMENT_STATE_NOT_REGISTERED]]],
            'missing status' => [[[]]],
            'unknown status' => [[['status' => 'OK']]],
            'invalid URL' => [[['status' => AdUser::REASSESSMENT_STATE_INVALID_URL]]],
            'error' => [[['status' => AdUser::REASSESSMENT_STATE_ERROR]]],
            'not registered' => [[['status' => AdUser::REASSESSMENT_STATE_NOT_REGISTERED]]],
            'processing' => [[['status' => AdUser::REASSESSMENT_STATE_PROCESSING]], true],
            'locked with invalid date' => [[[
                'status' => AdUser::REASSESSMENT_STATE_LOCKED,
                'reassess_available_at' => '2020-01-01 00:00:00',
            ]], true],
            'locked' => [[[
                'status' => AdUser::REASSESSMENT_STATE_LOCKED,
                'reassess_available_at' => (new DateTimeImmutable('+1 hour'))->format(DateTimeImmutable::ATOM),
            ]], true],
            'accepted' => [[['status' => AdUser::REASSESSMENT_STATE_ACCEPTED]], true],
        ];
    }

    public function testDbConnectionError(): void
    {
        $row = (object)[
            'id' => '1',
            'url' => 'https://example.com',
            'views' => 100000,
        ];
        DB::shouldReceive('select')->andReturns([$row], [], []);
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('update')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('commit')->never();
        DB::shouldReceive('rollback')->andReturnUndefined();

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('reassessPageRankBatch')
            ->willReturn([['status' => AdUser::REASSESSMENT_STATE_ACCEPTED]]);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    private static function insertStatsForTooManyViews(): void
    {
        /** @var Site $site */
        $site = factory(Site::class)->create(['reassess_available_at' => new DateTimeImmutable('-1 minute')]);
        $views = 100000;

        DB::insert(
            'INSERT INTO network_case_logs_hourly_stats (publisher_id,site_id,views_all,views) '
            . 'VALUES (0x00000000000000000000000000000001,?,?,?)',
            [hex2bin($site->uuid), $views, $views]
        );
    }
}
