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

use Adshares\Adserver\Models\Payment;
use Adshares\Common\Application\Service\Ads;
use Illuminate\Console\Command;

class DemandSendPayments extends Command
{
    protected $signature = 'ops:demand:payments:send';

    public function handle(Ads $ads): void
    {
        $payments = Payment::fetchByStatus(Payment::STATE_NEW, false);

        $paymentCount = count($payments);
        $this->info("Found $paymentCount sendable payments.");

        if (!$paymentCount) {
            return;
        }

        $this->info("Sending $paymentCount payments from ".config('app.adshares_address').'.');
        $tx = $ads->sendPayments($payments);

        $payments->each(function (Payment $payment) use ($tx) {
            $payment->tx_id = $tx->getId();
            $payment->tx_time = $tx->getTime()->getTimestamp();
            $payment->tx_data = $tx->getData();

            $payment->state = Payment::STATE_SENT;

            $payment->account_hashin = $tx->getAccountHashin();
            $payment->account_hashout = $tx->getAccountHashout();
            $payment->account_msid = $tx->getAccountMsid();

            $payment->save();

            $this->info("#{$payment->id}: {$payment->totalEventValue()} clicks to {$payment->account_address};");
        });

        $this->info("Spent {$tx->getDeduct()} clicks, including a {$tx->getFee()} clicks fee.");
    }
}
