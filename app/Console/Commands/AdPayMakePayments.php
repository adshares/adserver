<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdPayMakePayments extends Command
{
    private const EXIT_CODE_SUCCESS = 0;

    protected $signature = 'ops:adpay:payments:make';

    public function handle(): int
    {
        $events = EventLog::fetchUnpaidEvents();

        $eventCount = count($events);
        $this->info("Found $eventCount payable events.");

        if (!$eventCount) {
            return self::EXIT_CODE_SUCCESS;
        }

        $groupedEvents = $events->groupBy('pay_to');

        $this->info('In that, there are '.count($groupedEvents).' recipients.');

        DB::beginTransaction();

        $groupedEvents
            ->map(function (Collection $paymentGroup, string $key) {
                return [
                    'events' => $paymentGroup,
                    'account_address' => $key,
                    'state' => Payment::STATE_NEW,
                    'completed' => 0,
                ];
            })
            ->each(function (array $paymentData) {
                $payment = new Payment();
                $payment->fill($paymentData);
                $payment->push();

                $payment->events()->saveMany($paymentData['events']);
            });

        DB::commit();

        return self::EXIT_CODE_SUCCESS;
    }
}
