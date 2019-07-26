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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemandPreparePayments extends BaseCommand
{
    protected $signature = 'ops:demand:payments:prepare {--c|chunkSize=10000}';

    protected $description = 'Prepares payments for events and license';

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(Locker $locker, LicenseReader $licenseReader)
    {
        $this->licenseReader = $licenseReader;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        while (true) {
            $events = EventLog::fetchUnpaidEvents((int)$this->option('chunkSize'));

            $eventCount = count($events);
            $this->info("Found $eventCount payable events.");

            if (!$eventCount) {
                return;
            }

            $licenseAccountAddress = $this->licenseReader->getAddress()->toString();
            $demandLicenseFeeCoefficient = $this->licenseReader->getFee(Config::LICENCE_TX_FEE);
            $demandOperatorFeeCoefficient = Config::fetchFloatOrFail(Config::OPERATOR_TX_FEE);
            $groupedEvents = $this->processAndGroupByRecipient(
                $events,
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient
            );

            $this->info('In that, there are '.count($groupedEvents).' recipients,');

            DB::beginTransaction();

            $payments = $groupedEvents
                ->map(static function (Collection $paymentGroup, string $key) {
                    return [
                        'events' => $paymentGroup,
                        'account_address' => $key,
                        'state' => Payment::STATE_NEW,
                        'completed' => 0,
                    ];
                })->map(static function (array $paymentData) {
                    $payment = new Payment();
                    $payment->fill($paymentData);
                    $payment->push();
                    $payment->events()->saveMany($paymentData['events']);

                    return $payment;
                });

            $licensePayment = new Payment([
                'account_address' => $licenseAccountAddress,
                'state' => Payment::STATE_NEW,
                'completed' => 0,
                'fee' => $payments->sum(static function (Payment $payment) {
                    return $payment->totalLicenseFee();
                }),
            ]);

            $licensePayment->save();

            DB::commit();

            $this->info("and a license fee of {$licensePayment->fee} clicks"
                ." payable to {$licensePayment->account_address}.");
        }
    }

    private function processAndGroupByRecipient(
        \Illuminate\Database\Eloquent\Collection $events,
        float $demandLicenseFeeCoefficient,
        float $demandOperatorFeeCoefficient
    ): Collection {
        return $events->each(
            static function (EventLog $entry) use ($demandLicenseFeeCoefficient, $demandOperatorFeeCoefficient) {
                $licenseFee = (int)floor($entry->event_value * $demandLicenseFeeCoefficient);
                $entry->license_fee = $licenseFee;

                $amountAfterFee = $entry->event_value - $licenseFee;
                $operatorFee = (int)floor($amountAfterFee * $demandOperatorFeeCoefficient);
                $entry->operator_fee = $operatorFee;

                $entry->paid_amount = $amountAfterFee - $operatorFee;
            }
        )->groupBy('pay_to');
    }
}
