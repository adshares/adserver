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

use Adshares\Adserver\Models\AdvertiserBudget;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

use function array_merge;
use function factory;
use function json_decode;

class DemandBlockPaymentsTest extends ConsoleTestCase
{
    /** @var array */
    private $calculations = [];

    public function testZero(): void
    {
        $this->artisan('ops:demand:payments:block')
            ->expectsOutput('Attempt to create 0 blockades.')
            ->assertExitCode(0);
    }

    public function testBlock(): void
    {
        $this->mockExchangeRate();

        /** @var User $user */
        $user = factory(User::class)->create();

        self::createCampaigns($user);
        self::createLedgerEntries($user);

        self::assertEquals(1000 * 10 ** 11, $user->getBalance());
        self::assertEquals(500 * 10 ** 11, $user->getWalletBalance());
        self::assertEquals(500 * 10 ** 11, $user->getBonusBalance());

        $this->artisan('ops:demand:payments:block')
            ->expectsOutput('Attempt to create 1 blockades.')
            ->assertExitCode(0);

        self::assertEquals(500 * 10 ** 11, $user->getBalance());
        self::assertEquals(300 * 10 ** 11, $user->getWalletBalance());
        self::assertEquals(200 * 10 ** 11, $user->getBonusBalance());
    }

    private static function createCampaigns(
        User $user,
        int $withTargeting = 2,
        int $activeCount = 5
    ): SupportCollection {
        $withoutTargeting = $activeCount - $withTargeting;

        if ($withoutTargeting) {
            factory(Campaign::class)->times($withoutTargeting)->create([
                'user_id' => $user->id,
                'status' => Campaign::STATUS_ACTIVE,
            ]);
        }

        if ($withTargeting) {
            factory(Campaign::class)->times($withTargeting)->create([
                'user_id' => $user->id,
                'status' => Campaign::STATUS_ACTIVE,
                'targeting_requires' => json_decode('{"site": {"domain": ["www.adshares.net"]}}', true),
            ]);
        }

        return Campaign::all()->map(static function (Campaign $campaign) {
            /** @var Collection|EventLog[] $events */
            return factory(EventLog::class)->times(3)->create([
                'exchange_rate' => null,
                'event_value' => null,
                'event_value_currency' => null,
                'advertiser_id' => $campaign->user->uuid,
                'campaign_id' => $campaign->uuid,
            ]);
        })->flatten(1);
    }

    private static function createLedgerEntries(User $user): void
    {
        $entries = [
            [UserLedgerEntry::TYPE_DEPOSIT, 500 * 10 ** 11, UserLedgerEntry::STATUS_ACCEPTED],
            [UserLedgerEntry::TYPE_BONUS_INCOME, 500 * 10 ** 11, UserLedgerEntry::STATUS_ACCEPTED],
        ];

        foreach ($entries as $entry) {
            factory(UserLedgerEntry::class)->create([
                'type' => $entry[0],
                'amount' => $entry[1],
                'status' => $entry[2],
                'user_id' => $user->id,
            ]);
        }
    }

    public function values(): array
    {
        return [
            [0, 9850, 5000, 4850, 1],
            [2, 9850, 4940, 4910, 1],
            [5, 9850, 4850, 5000, 1],
            [0, 9985, 5000, 4985, 10],
            [2, 9985, 4994, 4991, 10],
            [5, 9985, 4985, 5000, 10],
        ];
    }

    /** @dataProvider values */
    public function testPay(int $targetingCount, int $balance, int $wallet, int $bonus, int $exchangeRate): void
    {
        $this->mockExchangeRate($exchangeRate);

        $user = $this->createStuff($targetingCount);

        $this->app->bind(
            AdPay::class,
            function () {
                $adPay = $this->createMock(AdPay::class);
                $adPay->method('getPayments')->willReturn($this->calculations);

                return $adPay;
            }
        );

        self::assertEquals(1000 * 10 ** 11, $user->getBalance());
        self::assertEquals(500 * 10 ** 11, $user->getWalletBalance());
        self::assertEquals(500 * 10 ** 11, $user->getBonusBalance());

        $this->artisan('ops:adpay:payments:get')
            ->assertExitCode(0);

        self::assertEquals($balance * 10 ** 10, $user->getBalance());
        self::assertEquals($wallet * 10 ** 10, $user->getWalletBalance());
        self::assertEquals($bonus * 10 ** 10, $user->getBonusBalance());
    }

    private function createStuff(int $withTargeting): User
    {
        $user = factory(User::class)->create();

        $events = self::createCampaigns($user, $withTargeting);
        self::createLedgerEntries($user);

        $calculatedEvents = $events->map(static function (EventLog $entry) {
            return [
                'event_id' => $entry->event_id,
                'event_type' => $entry->event_type,
                'value' => 10 ** 11,
                'status' => 0,
            ];
        });

        $this->calculations = array_merge($this->calculations, $calculatedEvents->all());

        return $user;
    }

    public function testFetchRequiredBudgetsPerUser(): void
    {
        self::createCampaigns(factory(User::class)->create());
        self::createCampaigns(factory(User::class)->create());
        self::createCampaigns(factory(User::class)->create());

        $budgets = Campaign::fetchRequiredBudgetsPerUser();

        self::assertCount(3, $budgets);

        $budgets->each(static function (AdvertiserBudget $budget) {
            self::assertEquals(500 * 10 ** 11, $budget->total());
            self::assertEquals(300 * 10 ** 11, $budget->bonusable());
        });
    }

    private function mockExchangeRate(int $value = 1): void
    {
        $this->app->bind(
            ExchangeRateReader::class,
            function () use ($value) {
                $mock = $this->createMock(ExchangeRateReader::class);

                $mock->method('fetchExchangeRate')
                    ->willReturn(new ExchangeRate(new DateTime(), $value, 'XXX'));

                return $mock;
            }
        );
    }
}
