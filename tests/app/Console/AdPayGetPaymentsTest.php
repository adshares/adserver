<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Console;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Demand\Application\Service\AdPay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function factory;
use function mt_rand;

class AdPayGetPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function testHandle(): void
    {
        /** @var Collection|EventLog[] $events */
        $events = factory(EventLog::class)->times(3)->create([
            'event_value' => null,
        ]);

        $calculatedEvents = $events->map(function (EventLog $entry) {
            return [
                'event_id' => $entry->event_id,
                'amount' => mt_rand(),
            ];
        });

        $this->app->bind(AdPay::class, function () use ($calculatedEvents) {
            $adsClient = $this->createMock(AdPay::class);
            $adsClient->method('getPayments')->willReturn($calculatedEvents->toArray());

            return $adsClient;
        });

        $this->artisan('ops:adpay:payments:get')
             ->assertExitCode(0)
             ->expectsOutput('Found 3 calculations.')
             ->expectsOutput('Updated 3 entries.');

        $calculatedEvents->each(function (array $eventValue) {
            $eventValue['event_id'] = hex2bin($eventValue['event_id']);

            $eventValue['event_value'] = $eventValue['amount'];
            unset($eventValue['amount']);

            $this->assertDatabaseHas('event_logs', $eventValue);
        });
    }
}
