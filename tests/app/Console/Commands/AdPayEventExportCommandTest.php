<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\TestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Demand\Application\Dto\AdPayEvents;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Dto\UserContext;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdPayEventExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testEmptyDb(): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                $adPay = $this->createMock(AdPay::class);
                $adPay->expects(self::atLeastOnce())->method('addViews');
                $adPay->expects(self::atLeastOnce())->method('addClicks');
                $adPay->expects(self::atLeastOnce())->method('addConversions');

                return $adPay;
            }
        );

        $this->artisan('ops:adpay:event:export')->assertExitCode(0);
    }
    
    public function testExportView(): void
    {
        $this->bindAdUser();

        $this->app->bind(
            AdPay::class,
            function () {
                $adPay = $this->createMock(AdPay::class);

                $adPay->expects(self::atLeastOnce())->method('addViews')->will(
                    self::checkEventCount(1)
                );
                $adPay->expects(self::atLeastOnce())->method('addClicks')->will(
                    self::checkEventCount(0)
                );
                $adPay->expects(self::atLeastOnce())->method('addConversions')->will(
                    self::checkEventCount(0)
                );

                return $adPay;
            }
        );

        $event = $this->insertEventView();
        $eventDate = $event->created_at->format(DateTime::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    private static function checkEventCount(int $eventCount)
    {
        return self::returnCallback(
            function (AdPayEvents $adPayEvents) use ($eventCount) {
                self::assertCount($eventCount, $adPayEvents->toArray()['events']);
            }
        );
    }

    public function testExportClick(): void
    {
        $this->bindAdUser();

        $this->app->bind(
            AdPay::class,
            function () {
                $adPay = $this->createMock(AdPay::class);

                $adPay->expects(self::atLeastOnce())->method('addViews')->will(
                    self::checkEventCount(0)
                );
                $adPay->expects(self::atLeastOnce())->method('addClicks')->will(
                    self::checkEventCount(1)
                );
                $adPay->expects(self::atLeastOnce())->method('addConversions')->will(
                    self::checkEventCount(0)
                );

                return $adPay;
            }
        );

        $event = $this->insertEventClick();
        $eventDate = $event->created_at->format(DateTime::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    /**
     * @dataProvider invalidOptionsProvider
     */
    public function testInvalidOptions(?string $from, ?string $to): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                $adPay = $this->createMock(AdPay::class);
                $adPay->expects(self::never())->method('addViews');
                $adPay->expects(self::never())->method('addClicks');
                $adPay->expects(self::never())->method('addConversions');

                return $adPay;
            }
        );

        $this->artisan('ops:adpay:event:export', ['--from' => $from, '--to' => $to])->assertExitCode(0);
    }

    public function invalidOptionsProvider(): array
    {
        return [
            'missing from' => [null, '2019-12-01'],
            'invalid from' => ['zzz', '2019-12-01'],
            'invalid to' => ['2019-12-01', 'zzz'],
        ];
    }

    private function bindAdUser(): void
    {
        $this->app->bind(
            AdUser::class,
            function () {
                $userContext = UserContext::fromAdUserArray(
                    [
                        'keywords' => [
                            'user' => [
                                'language' => ['en'],
                                'country' => 'en',
                            ],
                            'site' => [
                                "domain" => [
                                    'net',
                                    'example.net',
                                    'my.example.net',
                                ],
                            ],
                        ],
                        'human_score' => 0.9,
                        'uuid' => 'cb528636-3512-4e2f-b62c-d649c8b57a2e',
                    ]
                );
                $adUser = $this->createMock(AdUser::class);
                $adUser->method('getUserContext')->willReturn($userContext);

                return $adUser;
            }
        );
    }

    private function insertEventView(): EventLog
    {
        return $this->insertEvent(EventLog::TYPE_VIEW);
    }

    private function insertEventClick(): EventLog
    {
        return $this->insertEvent(EventLog::TYPE_CLICK);
    }

    private function insertEvent(string $eventType): EventLog
    {
        $user = factory(User::class)->create();
        $campaign = factory(Campaign::class)->create(
            [
                'user_id' => $user->id,
                'budget' => 100000000000,
            ]
        );

        return factory(EventLog::class)->create(
            [
                'event_type' => $eventType,
                'campaign_id' => $campaign->uuid,
            ]
        );
    }
}
