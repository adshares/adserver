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

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Common\Infrastructure\Service\LicenseFeeReader;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemandPreparePayments extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:demand:payments:prepare';

    /** @var LicenseFeeReader */
    private $licenseFeeReader;

    public function __construct(LicenseFeeReader $licenseFeeReader)
    {
        $this->licenseFeeReader = $licenseFeeReader;

        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        $events = EventLog::fetchUnpaidEvents();

        $eventCount = count($events);
        $this->info("Found $eventCount payable events.");

        if (!$eventCount) {
            return;
        }

        $demandFee = $this->licenseFeeReader->getFee(Config::LICENCE_TX_FEE);
        $groupedEvents = $events->each(function (EventLog $entry) use ($demandFee) {
            $entry->licence_fee = (int)floor($entry->event_value * $demandFee);

            $amountAfterFee = $entry->event_value - $entry->licenceFee;

            $entry->operator_fee = (int)floor($amountAfterFee * Config::fetch(Config::OPERATOR_TX_FEE));

            $entry->paid_amount = $amountAfterFee - $entry->operatorFee;
        })->groupBy('pay_to');

        $this->info('In that, there are '.count($groupedEvents).' recipients,');

        DB::beginTransaction();

        $payments = $groupedEvents->map(function (Collection $paymentGroup, string $key) {
            return [
                'events' => $paymentGroup,
                'account_address' => $key,
                'state' => Payment::STATE_NEW,
                'completed' => 0,
            ];
        })->map(function (array $paymentData) {
            $payment = new Payment();
            $payment->fill($paymentData);
            $payment->push();
            $payment->events()->saveMany($paymentData['events']);

            return $payment;
        });

        $licencePayment = new Payment([
            'account_address' => Config::fetch(Config::LICENCE_ACCOUNT),
            'state' => Payment::STATE_NEW,
            'completed' => 0,
            'fee' => $payments->sum(function (Payment $payment) {
                return $payment->totalLicenceFee();
            }),
        ]);

        $licencePayment->save();

        $this->info("and a licence fee of {$licencePayment->fee} clicks"
            ." payable to {$licencePayment->account_address}.");

        DB::commit();
    }
}
