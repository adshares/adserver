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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Demand\Application\Dto\AdPayEvents;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Dto\UserContext;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;

class AdPayEventExportCommandTest extends ConsoleTestCase
{
    public function testEmptyDb(): void
    {
        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::atLeastOnce())->method('addViews');
        $adPay->expects(self::atLeastOnce())->method('addClicks');
        $adPay->expects(self::atLeastOnce())->method('addConversions');
        $this->instance(AdPay::class, $adPay);

        $this->artisan('ops:adpay:event:export')->assertExitCode(0);
    }

    public function testExportView(): void
    {
        $event = $this->insertEventView();
        $event->domain = 'my.example.com';
        $event->saveOrFail();
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);
        $this->bindAdUser();
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_DEMAND_ENABLED => '1']);
        $adServerDomain = NetworkHost::fetchByAddress('0001-00000004-DBEB')->info->getAdsTxtDomain();
        $adsTxtCrawler = $this->createMock(AdsTxtCrawler::class);
        $adsTxtCrawler->expects(self::atLeastOnce())
            ->method('checkSite')
            ->with('https://my.example.com', $adServerDomain, $event->publisher_id)
            ->willReturn(true);
        $this->instance(AdsTxtCrawler::class, $adsTxtCrawler);

        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::atLeastOnce())
            ->method('addViews')
            ->will(
                self::returnCallback(
                    function (AdPayEvents $adPayEvents) {
                        $events = $adPayEvents->toArray()['events'];
                        self::assertCount(1, $events);
                        self::assertEquals(1, $events[0]['ads_txt']);
                    }
                )
            );
        $adPay->expects(self::atLeastOnce())->method('addClicks')->will(self::checkEventCount(0));
        $adPay->expects(self::atLeastOnce())->method('addConversions')->will(self::checkEventCount(0));
        $this->instance(AdPay::class, $adPay);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    public function testExportViewWhileEventPayToHasNoMatchingNetworkHost(): void
    {
        $event = $this->insertEventView();
        $event->domain = 'my.example.com';
        $event->pay_to = '0001-00000001-8B4E';
        $event->saveOrFail();
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);
        $this->bindAdUser();
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_DEMAND_ENABLED => '1']);
        $adsTxtCrawler = $this->createMock(AdsTxtCrawler::class);
        $adsTxtCrawler->expects(self::never())->method('checkSite');
        $this->instance(AdsTxtCrawler::class, $adsTxtCrawler);
        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::atLeastOnce())
            ->method('addViews')
            ->will(
                self::returnCallback(
                    function (AdPayEvents $adPayEvents) {
                        $events = $adPayEvents->toArray()['events'];
                        self::assertCount(1, $events);
                        self::assertEquals(0, $events[0]['ads_txt']);
                    }
                )
            );
        $adPay->expects(self::atLeastOnce())->method('addClicks')->will(self::checkEventCount(0));
        $adPay->expects(self::atLeastOnce())->method('addConversions')->will(self::checkEventCount(0));
        $this->instance(AdPay::class, $adPay);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    public function testExportViewWhileUserDataIsPresent(): void
    {
        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::never())->method('getUserContext');
        $this->app->bind(
            AdUser::class,
            function () use ($adUser) {
                return $adUser;
            }
        );

        $event = EventLog::factory()->create(
            [
                'campaign_id' => Campaign::factory()->create()->uuid,
                'event_type' => EventLog::TYPE_VIEW,
                'human_score' => 0.51,
                'our_userdata' => [],
            ]
        );
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    public function testExportViewWhileUserDataIsUnavailable(): void
    {
        Log::spy();
        $adUser = $this->createMock(AdUser::class);
        $adUser->expects(self::once())
            ->method('getUserContext')
            ->willThrowException(new RuntimeException('test-exception'));
        $this->app->bind(
            AdUser::class,
            function () use ($adUser) {
                return $adUser;
            }
        );

        $event = $this->insertEventView();
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
        Log::shouldHaveReceived('error')->once();
    }

    public function testExportViewWhileExternalUserIdIsMissingForDecentraland(): void
    {
        $event = $this->insertEventView();
        $event->domain = 'my.example.com';
        $event->medium = 'metaverse';
        $event->vendor = 'decentraland';
        $event->saveOrFail();
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);
        $this->bindAdUser();

        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::atLeastOnce())
            ->method('addViews')
            ->will(
                self::returnCallback(
                    function (AdPayEvents $adPayEvents) {
                        $events = $adPayEvents->toArray()['events'];
                        self::assertCount(1, $events);
                        self::assertEquals(
                            0,
                            $events[0]['human_score'],
                            sprintf(
                                'Failed asserting that human_score %s matches expected 0.',
                                $events[0]['human_score'],
                            ),
                        );
                    }
                )
            );
        $adPay->expects(self::atLeastOnce())->method('addClicks')->will(self::checkEventCount(0));
        $adPay->expects(self::atLeastOnce())->method('addConversions')->will(self::checkEventCount(0));
        $this->instance(AdPay::class, $adPay);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    public function testExportViewAndConversionWhileTooManyEventsInSecond(): void
    {
        $this->bindAdUser();

        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::never())->method('addViews');
        $adPay->expects(self::never())->method('addClicks');
        $adPay->expects(self::never())->method('addConversions');
        $this->instance(AdPay::class, $adPay);

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create();
        $createdAt = new DateTimeImmutable();
        $events = EventLog::factory()->count(501)->create(
            [
                'campaign_id' => $campaign->uuid,
                'event_type' => EventLog::TYPE_VIEW,
                'created_at' => $createdAt,
            ]
        );
        $conversionDefinition = new ConversionDefinition();
        $conversionDefinition->fill(
            [
                'campaign_id' => $campaign->id,
                'name' => 'basic-1',
                'event_type' => 'Purchase',
                'type' => ConversionDefinition::BASIC_TYPE,
                'value' => 1000000000,
                'limit' => 100000000000,
                'limit_type' => 'in_budget',
                'is_repeatable' => false,
                'is_value_mutable' => false,
            ]
        );
        $campaign->conversions()->save($conversionDefinition);

        foreach ($events as $event) {
            Conversion::factory()->create(
                [
                    'conversion_definition_id' => $conversionDefinition->id,
                    'created_at' => $createdAt,
                    'event_logs_id' => $event->id,
                    'pay_to' => '0001-00000001-8B4E',
                ]
            );
        }
        $eventDate = $createdAt->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    public function testExportViewRecoverableAdPayError(): void
    {
        $this->bindAdUser();

        $adPay = self::createMock(AdPay::class);
        $adPay->expects(self::exactly(2))->method('addViews')->willReturnOnConsecutiveCalls(
            self::throwException(new UnexpectedClientResponseException('test-exception')),
        );
        $this->instance(AdPay::class, $adPay);

        $dateTo = new DateTimeImmutable();
        $dateFrom = $dateTo->modify('-5 minutes');
        self::insertTwoPackagesOfViewEvent($dateFrom, $dateTo);
        $from = $dateFrom->format(DateTimeInterface::ATOM);
        $to = $dateTo->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $from, '--to' => $to, '--threads' => 2])
            ->assertExitCode(0);
    }

    public function testExportViewNotRecoverableAdPayError(): void
    {
        $this->bindAdUser();

        $adPay = self::createMock(AdPay::class);
        $adPay->method('addViews')->willThrowException(
            new UnexpectedClientResponseException('test-exception'),
        );
        $this->instance(AdPay::class, $adPay);

        $dateTo = new DateTimeImmutable();
        $dateFrom = $dateTo->modify('-5 minutes');
        self::insertTwoPackagesOfViewEvent($dateFrom, $dateTo);
        $from = $dateFrom->format(DateTimeInterface::ATOM);
        $to = $dateTo->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $from, '--to' => $to, '--threads' => 2])
            ->assertExitCode(1);
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

        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::atLeastOnce())->method('addViews')->will(self::checkEventCount(0));
        $adPay->expects(self::atLeastOnce())->method('addClicks')->will(self::checkEventCount(1));
        $adPay->expects(self::atLeastOnce())->method('addConversions')->will(self::checkEventCount(0));
        $this->instance(AdPay::class, $adPay);

        $event = $this->insertEventClick();
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    public function testExportConversion(): void
    {
        $this->bindAdUser();

        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::atLeastOnce())->method('addViews')->will(self::checkEventCount(0));
        $adPay->expects(self::atLeastOnce())->method('addClicks')->will(self::checkEventCount(0));
        $adPay->expects(self::atLeastOnce())->method('addConversions')->will(self::checkEventCount(1));
        $this->instance(AdPay::class, $adPay);

        $event = $this->insertEventConversion();
        $eventDate = $event->created_at->format(DateTimeInterface::ATOM);

        $this->artisan('ops:adpay:event:export', ['--from' => $eventDate, '--to' => $eventDate])->assertExitCode(0);
    }

    /**
     * @dataProvider invalidOptionsProvider
     *
     * @param string|null $from
     * @param string|null $to
     */
    public function testInvalidOptions(?string $from, ?string $to): void
    {
        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::never())->method('addViews');
        $adPay->expects(self::never())->method('addClicks');
        $adPay->expects(self::never())->method('addConversions');
        $this->instance(AdPay::class, $adPay);

        $this->artisan('ops:adpay:event:export', ['--from' => $from, '--to' => $to])
            ->assertExitCode(1);
    }

    public function invalidOptionsProvider(): array
    {
        return [
            'missing from' => [null, '2019-12-01'],
            'invalid from' => ['zzz', '2019-12-01'],
            'invalid to' => ['2019-12-01', 'zzz'],
            'invalid range' => ['2019-12-01 12:00:01', '2019-12-01 12:00:00'],
        ];
    }

    public function testInvalidOptionsConversionRange(): void
    {
        Config::updateAdminSettings([
            Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME => (new DateTimeImmutable())->format(DateTimeInterface::ATOM)
        ]);
        $adPay = $this->createMock(AdPay::class);
        $adPay->expects(self::never())->method('addViews');
        $adPay->expects(self::never())->method('addClicks');
        $adPay->expects(self::never())->method('addConversions');
        $this->instance(AdPay::class, $adPay);

        $this->artisan('ops:adpay:event:export')
            ->assertExitCode(1);
    }

    public function testLock(): void
    {
        $lock = new Lock(new Key('ops:adpay:event:export'), new FlockStore(), null, false);
        $lock->acquire();

        $this->artisan('ops:adpay:event:export')
            ->assertExitCode(1);
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

    private function insertEventConversion(): Conversion
    {
        $campaign = Campaign::factory()->create(
            [
                'budget' => 100000000000,
            ]
        );

        /** @var EventLog $eventLog */
        $eventLog = EventLog::factory()->create(
            [
                'created_at' => new DateTimeImmutable('-1 hour'),
                'event_type' => EventLog::TYPE_VIEW,
                'campaign_id' => $campaign->uuid,
            ]
        );

        $conversionDefinition = new ConversionDefinition();
        $conversionDefinition->fill(
            [
                'campaign_id' => $campaign->id,
                'name' => 'basic-1',
                'event_type' => 'Purchase',
                'type' => ConversionDefinition::BASIC_TYPE,
                'value' => 1000000000,
                'limit' => 100000000000,
                'limit_type' => 'in_budget',
                'is_repeatable' => false,
                'is_value_mutable' => false,
            ]
        );

        $campaign->conversions()->save($conversionDefinition);

        Conversion::register(
            $eventLog->case_id,
            Uuid::v4()->toString(),
            $eventLog->id,
            $conversionDefinition->id,
            $conversionDefinition->value,
            1,
            '0001-00000001-8B4E'
        );

        return Conversion::first();
    }

    private function insertEvent(string $eventType): EventLog
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'budget' => 100000000000,
            ]
        );
        NetworkHost::factory()->create(
            [
                'address' => '0001-00000004-DBEB',
            ]
        );

        return EventLog::factory()->create(
            [
                'event_type' => $eventType,
                'campaign_id' => $campaign->uuid,
                'pay_to' => '0001-00000004-DBEB',
            ]
        );
    }

    private static function insertTwoPackagesOfViewEvent(DateTimeInterface $from, DateTimeInterface $to): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'budget' => 100000000000,
            ]
        );

        $count = 1000;
        $start = $from->getTimestamp();
        $period = $to->getTimestamp() - $start;
        $delay = $period / $count;

        EventLog::factory()
            ->count($count)
            ->sequence(function ($sequence) use ($start, $delay) {
                $timestamp = $start + (int)floor($sequence->index * $delay);
                return ['created_at' => new DateTimeImmutable('@' . $timestamp)];
            })
            ->create(
                [
                    'event_type' => EventLog::TYPE_VIEW,
                    'campaign_id' => $campaign->uuid,
                ]
            );
    }
}
