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

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use DateTime;
use Illuminate\Database\Eloquent\Collection;

use function factory;
use function json_decode;
use function random_int;

class AdPayGetPaymentsTest extends ConsoleTestCase
{
    public function testHandle(): void
    {
        $dummyExchangeRateRepository = new DummyExchangeRateRepository();

        $this->app->bind(
            ExchangeRateRepository::class,
            static function () use ($dummyExchangeRateRepository) {
                return $dummyExchangeRateRepository;
            }
        );

        $user = factory(User::class)->create();
        $userId = $user->id;
        $userUuid = $user->uuid;

        $campaign = factory(Campaign::class)->create(['user_id' => $userId, 'budget' => 10 ** 7 * 10 ** 11]);
        $campaignId = $campaign->id;
        $campaignUuid = $campaign->uuid;

        $banner = factory(Banner::class)->create(['campaign_id' => $campaignId]);
        $bannerUuid = $banner->uuid;

        /** @var Collection|EventLog[] $events */
        $events = factory(EventLog::class)->times(666)->create([
            'event_value_currency' => null,
            'advertiser_id' => $userUuid,
            'campaign_id' => $campaignUuid,
            'banner_id' => $bannerUuid,
        ]);

        $calculatedEvents = $events->map(static function (EventLog $entry) {
            return [
                'event_id' => $entry->event_id,
                'event_type' => $entry->event_type,
                'value' => random_int(0, 100 * 10 ** 11),
                'status' => 0,
            ];
        });

        $totalInCurrency = $calculatedEvents->sum('value');
        $userBalance = (int)ceil(
            $totalInCurrency / $dummyExchangeRateRepository->fetchExchangeRate(new DateTime())->getValue()
        );
        factory(UserLedgerEntry::class)->create(['amount' => $userBalance, 'user_id' => $userId]);

        $this->app->bind(
            AdPay::class,
            function () use ($calculatedEvents) {
                $adPay = $this->createMock(AdPay::class);
                $adPay->method('getPayments')->will($this->returnCallback(
                    function ($timestamp, $recalculate, $force, $limit, $offset) use ($calculatedEvents) {
                        return $calculatedEvents->slice($offset, $limit)->toArray();
                    }
                ));

                return $adPay;
            }
        );

        $this->artisan('ops:adpay:payments:get')
            ->assertExitCode(0);

        $calculatedEvents->each(function (array $eventValue) {
            $eventValue['event_id'] = hex2bin($eventValue['event_id']);

            $eventValue['event_value_currency'] = $eventValue['value'];
            unset($eventValue['value']);
            $eventValue['payment_status'] = $eventValue['status'];
            unset($eventValue['status']);

            $this->assertDatabaseHas('event_logs', $eventValue);
        });
    }

    public function testNormalization(): void
    {
        /** @var User $user */
        $user = factory(User::class)->times(1)->create()->each(static function (User $user) {
            $entries = [
                [UserLedgerEntry::TYPE_DEPOSIT, 100, UserLedgerEntry::STATUS_ACCEPTED],
                [UserLedgerEntry::TYPE_BONUS_INCOME, 100, UserLedgerEntry::STATUS_ACCEPTED],
            ];

            foreach ($entries as $entry) {
                factory(UserLedgerEntry::class)->create([
                    'type' => $entry[0],
                    'amount' => $entry[1],
                    'status' => $entry[2],
                    'user_id' => $user->id,
                ]);
            }

            factory(Campaign::class)->create([
                'user_id' => $user->id,
                'budget' => 100,
                'status' => Campaign::STATUS_ACTIVE,
            ]);
            factory(Campaign::class)->create([
                'user_id' => $user->id,
                'budget' => 100,
                'status' => Campaign::STATUS_ACTIVE,
                'targeting_requires' => json_decode('{"site": {"domain": ["www.adshares.net"]}}', true),
            ]);

            Campaign::all()->each(static function (Campaign $campaign) {
                $banner = factory(Banner::class)->create([
                    'campaign_id' => $campaign->id,
                ]);

                factory(EventLog::class)->times(1)->create([
                    'event_value_currency' => null,
                    'advertiser_id' => $campaign->user->uuid,
                    'campaign_id' => $campaign->uuid,
                    'banner_id' => $banner->uuid,
                ]);

                if (!$campaign->isDirectDeal()) {
                    factory(EventLog::class)->times(1)->create([
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

        $this->artisan('ops:adpay:payments:get')
            ->assertExitCode(0);

        $events = EventLog::all();

        self::assertEquals(200, $events->sum('event_value_currency'));
        self::assertEquals(3, $events->count());
        self::assertEquals(0, $user->getBalance());
    }
}
