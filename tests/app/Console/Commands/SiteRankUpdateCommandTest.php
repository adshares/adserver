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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Exception;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;

class SiteRankUpdateCommandTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:supply:site-rank:update';

    public function testEmpty(): void
    {
        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        self::assertSiteRankEventDispatched(0);
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    public function testUpdateSitesInVerification(): void
    {
        /** @var Site $siteInVerification */
        $siteInVerification = Site::factory()->create(['info' => AdUser::PAGE_INFO_UNKNOWN]);
        Site::factory()->create();

        $adUser = $this->createMock(AdUser::class);
        $expected = [
            [
                'url' => $siteInVerification->url,
                'categories' => $siteInVerification->categories,
            ],
        ];
        $adUser->expects(self::once())
            ->method('fetchPageRankBatch')
            ->with(self::equalTo($expected))
            ->willReturn(
                [
                    [
                        'rank' => 0,
                        'info' => AdUser::PAGE_INFO_UNKNOWN,
                        'categories' => ['unknown'],
                    ],
                ]
            );
        $this->instance(AdUser::class, $adUser);

        Mail::shouldReceive('to')->never();

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        self::assertSiteRankEventDispatched(1);
    }

    public function testUpdateSitesAll(): void
    {
        /** @var Site $siteInVerification */
        $siteInVerification = Site::factory()->create(['info' => AdUser::PAGE_INFO_UNKNOWN]);
        /** @var Site $siteVerified */
        $siteVerified = Site::factory()->create();

        $adUser = $this->createMock(AdUser::class);
        $expected = [
            [
                'url' => $siteInVerification->url,
                'categories' => $siteInVerification->categories,
            ],
            [
                'url' => $siteVerified->url,
                'categories' => $siteVerified->categories,
            ],
        ];
        $adUser->expects(self::once())
            ->method('fetchPageRankBatch')
            ->with(self::equalTo($expected))
            ->willReturn(
                [
                    [
                        'rank' => 0,
                        'info' => AdUser::PAGE_INFO_UNKNOWN,
                        'categories' => ['unknown'],
                    ],
                ],
                [
                    [
                        'rank' => 1,
                        'info' => AdUser::PAGE_INFO_OK,
                        'categories' => ['adult'],
                    ],
                ]
            );
        $this->instance(AdUser::class, $adUser);

        Mail::shouldReceive('to')->never();

        $this->artisan(self::SIGNATURE, ['--all' => true])->assertExitCode(0);
        self::assertSiteRankEventDispatched(2);
    }

    /**
     * @dataProvider adUserExceptionProvider
     *
     * @param Exception $exception
     */
    public function testAdUserException(Exception $exception): void
    {
        Site::factory()->create(['info' => AdUser::PAGE_INFO_UNKNOWN]);

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('fetchPageRankBatch')
            ->willThrowException($exception);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        self::assertSiteRankEventDispatched(1);
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
     */
    public function testAdUserResponse(array $response): void
    {
        Site::factory()->create(['info' => AdUser::PAGE_INFO_UNKNOWN]);

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('fetchPageRankBatch')
            ->willReturn($response);
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        self::assertSiteRankEventDispatched(1);
    }

    public function adUserResponseProvider(): array
    {
        return [
            'invalid URL' => [[['error' => 'Invalid URL']]],
            'invalid index' => [
                [
                    '100' => [
                        'rank' => 0.02,
                        'info' => AdUser::PAGE_INFO_OK,
                        'categories' => ['unknown'],
                    ],
                ],
            ],
            'missing rank' => [[['info' => AdUser::PAGE_INFO_OK]]],
            'missing info' => [[['rank' => 0.02]]],
            'ok' => [[['rank' => 0.02, 'info' => AdUser::PAGE_INFO_OK, 'categories' => ['unknown']]]],
            'ok without categories' => [[['rank' => 0.02, 'info' => AdUser::PAGE_INFO_OK]]],
            'ok with invalid categories' => [[['rank' => 0.02, 'info' => AdUser::PAGE_INFO_OK, 'categories' => ['0']]]],
        ];
    }

    public function testIncompleteSite(): void
    {
        Site::factory()->create(['info' => AdUser::PAGE_INFO_UNKNOWN, 'categories' => null]);

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::never())->method('fetchPageRankBatch');
        $this->instance(AdUser::class, $adUser);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
        self::assertSiteRankEventDispatched(1);
    }

    public function testEmailSend(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id, 'info' => AdUser::PAGE_INFO_UNKNOWN]);

        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('fetchPageRankBatch')
            ->willReturn([['rank' => 1, 'info' => AdUser::PAGE_INFO_OK]]);
        $this->instance(AdUser::class, $adUser);

        $pendingMail = $this->createMock(PendingMail::class);
        $pendingMail->method('send');
        Mail::shouldReceive('to')->once()->with($user->email)->andReturn($pendingMail);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $dbSite = Site::find($site->id);
        self::assertEquals(1, $dbSite->rank);
        self::assertEquals(AdUser::PAGE_INFO_OK, $dbSite->info);
        self::assertSiteRankEventDispatched(1);
    }

    private static function assertSiteRankEventDispatched(int $processedCount): void
    {
        self::assertServerEventDispatched(ServerEventType::SiteRankUpdated, ['processedSiteCount' => $processedCount]);
    }
}
