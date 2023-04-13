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

use Adshares\Adserver\Console\Commands\AdPayGetPayments;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Exceptions\Demand\AdPayReportMissingEventsException;
use Adshares\Adserver\Exceptions\Demand\AdPayReportNotReadyException;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use DateTime;
use Illuminate\Database\Eloquent\Collection;

use function json_decode;

class AdPayGetPaymentsTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:adpay:payments:get';

    public function testHandle(): void
    {
        $dummyExchangeRateRepository = new DummyExchangeRateRepository();

        $this->app->bind(
            ExchangeRateRepository::class,
            static function () use ($dummyExchangeRateRepository) {
                return $dummyExchangeRateRepository;
            }
        );

        /** @var User $user */
        $user = User::factory()->create();
        $userId = $user->id;
        $userUuid = $user->uuid;

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $userId, 'budget' => 10 ** 7 * 10 ** 11]);
        $campaignId = $campaign->id;
        $campaignUuid = $campaign->uuid;

        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaignId]);
        $bannerUuid = $banner->uuid;

        /** @var Collection|EventLog[] $events */
        $events = EventLog::factory()->times(666)->create([
            'event_value_currency' => null,
            'advertiser_id' => $userUuid,
            'campaign_id' => $campaignUuid,
            'banner_id' => $bannerUuid,
        ]);

        $i = 0;
        $calculatedEvents = $events->map(static function (EventLog $entry) use (&$i) {
            return [
                'event_id' => $entry->event_id,
                'event_type' => $entry->event_type,
                'value' => $i++,
                'status' => 0,
            ];
        });
        // event value sum = 221_445 / 0,3333 = 664_401
        UserLedgerEntry::factory()->create(
            [
                'amount' => 200_000,
                'user_id' => $userId,
                'type' => UserLedgerEntry::TYPE_DEPOSIT
            ]
        );
        UserLedgerEntry::factory()->create(
            [
                'amount' => 465_000,
                'user_id' => $userId,
                'type' => UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT
            ]
        );

        $this->assertEquals(665_000, $user->getWalletBalance());
        $this->assertEquals(200_000, $user->getWithdrawableBalance());

        $this->app->bind(
            AdPay::class,
            function () use ($calculatedEvents) {
                $adPay = $this->createMock(AdPay::class);
                $adPay->method('getPayments')->will(
                    $this->returnCallback(
                        function ($timestamp, $recalculate, $force, $limit, $offset) use ($calculatedEvents) {
                            return $calculatedEvents->slice($offset, $limit)->toArray();
                        }
                    )
                );

                return $adPay;
            }
        );

        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0);

        $calculatedEvents->each(function (array $eventValue) {
            $eventValue['event_id'] = hex2bin($eventValue['event_id']);

            $eventValue['event_value_currency'] = $eventValue['value'];
            unset($eventValue['value']);
            $eventValue['payment_status'] = $eventValue['status'];
            unset($eventValue['status']);

            $this->assertDatabaseHas('event_logs', $eventValue);
        });

        $this->assertEquals(599, $user->getWalletBalance());
        $this->assertEquals(599, $user->getWithdrawableBalance());
    }

    public function testNormalization(): void
    {
        /** @var User $user */
        $user = User::factory()->times(1)->create()->each(static function (User $user) {
            $entries = [
                [UserLedgerEntry::TYPE_DEPOSIT, 100, UserLedgerEntry::STATUS_ACCEPTED],
                [UserLedgerEntry::TYPE_BONUS_INCOME, 50, UserLedgerEntry::STATUS_ACCEPTED],
                [UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT, 50, UserLedgerEntry::STATUS_ACCEPTED],
            ];

            foreach ($entries as $entry) {
                UserLedgerEntry::factory()->create([
                    'type' => $entry[0],
                    'amount' => $entry[1],
                    'status' => $entry[2],
                    'user_id' => $user->id,
                ]);
            }

            Campaign::factory()->create([
                'user_id' => $user->id,
                'budget' => 100,
                'status' => Campaign::STATUS_ACTIVE,
            ]);
            Campaign::factory()->create([
                'user_id' => $user->id,
                'budget' => 100,
                'status' => Campaign::STATUS_ACTIVE,
                'targeting_requires' => json_decode('{"site": {"domain": ["www.adshares.net"]}}', true),
            ]);

            Campaign::all()->each(static function (Campaign $campaign) {
                /** @var Banner $banner */
                $banner = Banner::factory()->create([
                    'campaign_id' => $campaign->id,
                ]);

                EventLog::factory()->times(1)->create([
                    'event_value_currency' => null,
                    'advertiser_id' => $campaign->user->uuid,
                    'campaign_id' => $campaign->uuid,
                    'banner_id' => $banner->uuid,
                ]);

                if (!$campaign->isDirectDeal()) {
                    EventLog::factory()->times(1)->create([
                        'event_value_currency' => null,
                        'advertiser_id' => $campaign->user->uuid,
                        'campaign_id' => $campaign->uuid,
                        'banner_id' => $banner->uuid,
                    ]);
                }
            });
        })->first();

        $this->app->bind(
            AdPay::class,
            function () {
                $calculatedEvents = EventLog::all()->map(static function (EventLog $entry) {
                    return [
                        'event_id' => $entry->event_id,
                        'event_type' => $entry->event_type,
                        'value' => 100,
                        'status' => 0,
                    ];
                });

                $adPay = $this->createMock(AdPay::class);
                $adPay->method('getPayments')->willReturn($calculatedEvents->toArray());

                return $adPay;
            }
        );

        $this->app->bind(
            ExchangeRateReader::class,
            function () {
                $mock = $this->createMock(ExchangeRateReader::class);
                $mock->method('fetchExchangeRate')
                    ->willReturn(new ExchangeRate(new DateTime(), 1, 'XXX'));

                return $mock;
            }
        );

        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(0);

        $events = EventLog::all();

        self::assertEquals(200, $events->sum('event_value_currency'));
        self::assertEquals(3, $events->count());
        self::assertEquals(0, $user->getBalance());
    }

    /**
     * @dataProvider currencyProvider
     */
    public function testCurrency(Currency $currency, int $valueInCurrency, float $rate, int $expectedValue): void
    {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);

        /** @var User $user */
        $user = User::factory()->create();
        UserLedgerEntry::factory()->create(['amount' => 5 * 10 ** 11, 'user_id' => $user->id]);

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user->id, 'budget' => 10 ** 11]);

        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaign->id]);

        /** @var EventLog $event */
        $event = EventLog::factory()->create([
            'advertiser_id' => $user->uuid,
            'campaign_id' => $campaign->uuid,
            'banner_id' => $banner->uuid,
            'event_type' => EventLog::TYPE_VIEW,
            'event_value_currency' => null,
        ]);

        $calculatedEvent = [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'value' => $valueInCurrency,
            'status' => 0,
        ];

        $this->app->bind(
            AdPay::class,
            function () use ($calculatedEvent) {
                $mock = $this->createMock(AdPay::class);
                $mock->method('getPayments')->will(
                    $this->returnCallback(
                        function ($timestamp, $recalculate, $force, $limit, $offset) use ($calculatedEvent) {
                            return $offset > 0 ? [] : [$calculatedEvent];
                        }
                    )
                );
                return $mock;
            }
        );

        self::artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(AdPayGetPayments::STATUS_OK);

        self::assertDatabaseHas(
            'event_logs',
            [
                'event_id' => hex2bin($calculatedEvent['event_id']),
                'event_value' => $expectedValue,
                'event_value_currency' => $calculatedEvent['value'],
                'exchange_rate' => $rate,
                'payment_status' => $calculatedEvent['status'],
            ]
        );
    }

    public function currencyProvider(): array
    {
        return [
            'ADS' => [Currency::ADS, 1_000_000_000, 0.3333, 3_000_300_030],
            'USD' => [Currency::USD, 1_000_000_000, 1.0, 1_000_000_000],
        ];
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(AdPayGetPayments::STATUS_LOCKED);
    }

    public function testHandleAdPayExceptionOnReportNotReady(): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                $mock = $this->createMock(AdPay::class);
                $mock->method('getPayments')->willThrowException(
                    new AdPayReportNotReadyException('Test exception')
                );
                return $mock;
            }
        );

        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(AdPayGetPayments::STATUS_CLIENT_EXCEPTION);
    }

    public function testHandleAdPayExceptionOnMissingEvents(): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                $mock = $this->createMock(AdPay::class);
                $mock->method('getPayments')->willThrowException(
                    new AdPayReportMissingEventsException('Test exception')
                );
                return $mock;
            }
        );

        $this->artisan(self::COMMAND_SIGNATURE)
            ->assertExitCode(AdPayGetPayments::STATUS_REQUEST_FAILED);
    }

    public function testHandleWhileExchangeRateNotAvailable(): void
    {
        $locker = $this->createMock(Locker::class);
        $locker->expects(self::once())->method('lock')->willReturn(true);
        $locker->expects(self::once())->method('release');
        $this->instance(Locker::class, $locker);
        $this->app->bind(
            ExchangeRateReader::class,
            function () {
                $mock = $this->createMock(ExchangeRateReader::class);
                $mock->method('fetchExchangeRate')->willThrowException(
                    new ExchangeRateNotAvailableException('text-exception')
                );
                return $mock;
            }
        );

        self::expectException(ExchangeRateNotAvailableException::class);

        $this->artisan(self::COMMAND_SIGNATURE);
    }
}
