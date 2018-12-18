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
use Adshares\Demand\Application\Service\AdPay;
use Illuminate\Console\Command;
use function collect;
use function now;

class AdPayGetPayments extends Command
{
    protected $signature = 'ops:adpay:payments:get';

    public function handle(AdPay $adPay): void
    {
        $calculations = collect($adPay->getPayments(now()->getTimestamp()));

        $this->info('Found '.count($calculations).' calculations.');

        $idList = $calculations->map(function (array $amount) {
            return hex2bin($amount['event_id']);
        });

        $entries = EventLog::whereIn('event_id', $idList)
            ->get()
            ->each(function (EventLog $entry) use ($calculations) {
                $calculation = $calculations->firstWhere('event_id', $entry->event_id);

                $entry->update(['event_value' => $calculation['amount']]);
            });

        $this->info('Updated '.count($entries).' entries.');
    }
}
