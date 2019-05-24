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

use Adshares\Adserver\Client\DummyExchangeRateRepository;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function factory;

class AdPayGetPaymentsTest extends CommandTestCase
{
    use RefreshDatabase;

    public function testHandle(): void
    {
        $dummyExchangeRateRepository = new DummyExchangeRateRepository();

        $this->app->bind(
            ExchangeRateRepository::class,
            function () use ($dummyExchangeRateRepository) {
                return $dummyExchangeRateRepository;
            }
        );

        $user = factory(User::class)->create();
        $userId = $user->id;
        $userUuid = $user->uuid;

        $campaign = factory(Campaign::class)->create(['user_id' => $userId, 'budget' => 1000000000000000000]);
        $campaignId = $campaign->id;
        $campaignUuid = $campaign->uuid;

        $banner = factory(Banner::class)->create(['campaign_id' => $campaignId]);
        $bannerUuid = $banner->uuid;

        /** @var Collection|EventLog[] $events */
        $events = factory(EventLog::class)->times(3)->create([
            'event_value_currency' => null,
            'advertiser_id' => $userUuid,
            'campaign_id' => $campaignUuid,
            'banner_id' => $bannerUuid,
        ]);

        $calculatedEvents = $events->map(static function (EventLog $entry) {
            return [
                'event_id' => $entry->event_id,
                'amount' => random_int(0, 1000 * 10 ** 11),
                'reason' => 0,
            ];
        });

        $totalInCurrency = $calculatedEvents->sum('amount');
        $userBalance = (int)ceil(
            $totalInCurrency / $dummyExchangeRateRepository->fetchExchangeRate(new DateTime())->getValue()
        );
        factory(UserLedgerEntry::class)->create(['amount' => $userBalance, 'user_id' => $userId]);

        $this->app->bind(
            AdPay::class,
            function () use ($calculatedEvents) {
                $adPay = $this->createMock(AdPay::class);
                $adPay->method('getPayments')->willReturn($calculatedEvents->toArray());

                return $adPay;
            }
        );

        $this->artisan('ops:adpay:payments:get')
            ->assertExitCode(0);

        $calculatedEvents->each(function (array $eventValue) {
            $eventValue['event_id'] = hex2bin($eventValue['event_id']);

            $eventValue['event_value_currency'] = $eventValue['amount'];
            unset($eventValue['amount']);

            $this->assertDatabaseHas('event_logs', $eventValue);
        });
    }
}
