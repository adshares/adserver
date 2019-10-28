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
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemandPreparePayments extends BaseCommand
{
    public const COMMAND_SIGNATURE = 'ops:demand:payments:prepare';

    protected $signature = self::COMMAND_SIGNATURE.'
                            {--c|chunkSize=10000}
                            {--f|from= : Date from which unpaid events will be searched}
                            {--t|to= : Date to which unpaid events will be searched}';

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
            $this->info('Command '.self::COMMAND_SIGNATURE.' already running');

            return;
        }

        $this->info('Start command '.self::COMMAND_SIGNATURE);
        $from = $this->getDateTimeFromOption('from') ?: new DateTime('-24 hour');
        $to = $this->getDateTimeFromOption('to');
        if ($from !== null && $to !== null && $to < $from) {
            throw new InvalidArgumentException(
                sprintf(
                    '[DemandPreparePayments] Invalid period from (%s) to (%s)',
                    $from->format(DateTime::ATOM),
                    $to->format(DateTime::ATOM)
                )
            );
        }

        $licenseAccountAddress = $this->licenseReader->getAddress()->toString();
        $demandLicenseFeeCoefficient = $this->licenseReader->getFee(Config::LICENCE_TX_FEE);
        $demandOperatorFeeCoefficient = Config::fetchFloatOrFail(Config::OPERATOR_TX_FEE);

        $conversions = Conversion::fetchUnpaidConversions($from, $to);
        $conversionCount = count($conversions);
        $this->info("Found $conversionCount payable conversions.");
        if ($conversionCount > 0) {
            $totalLicenseFee = 0;
            $groupedConversions = $this->processAndGroupConversionsByRecipient(
                $conversions,
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                $totalLicenseFee
            );

            $this->info(sprintf('In that, there are %d recipients,', count($groupedConversions)));

            DB::beginTransaction();

            $groupedConversions->each(
                static function (Collection $paymentGroup, string $payTo) {
                    $paymentData = [
                        'events' => $paymentGroup,
                        'account_address' => $payTo,
                        'state' => Payment::STATE_NEW,
                        'completed' => 0,
                    ];

                    $payment = new Payment();
                    $payment->fill($paymentData);
                    $payment->push();
                    $payment->conversions()->saveMany($paymentGroup);
                }
            );

            $licensePayment = new Payment([
                'account_address' => $licenseAccountAddress,
                'state' => Payment::STATE_NEW,
                'completed' => 0,
                'fee' => $totalLicenseFee,
            ]);

            $licensePayment->save();

            DB::commit();

            $this->info("and a license fee of {$licensePayment->fee} clicks"
                ." payable to {$licensePayment->account_address}.");
        }
        while (true) {
            $events = EventLog::fetchUnpaidEvents($from, $to, (int)$this->option('chunkSize'));

            $eventCount = count($events);
            $this->info("Found $eventCount payable events.");

            if (!$eventCount) {
                $this->release();

                return;
            }

            $totalLicenseFee = 0;
            $groupedEvents = $this->processAndGroupByRecipient(
                $events,
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                $totalLicenseFee
            );

            $this->info(sprintf('In that, there are %d recipients,', count($groupedEvents)));

            DB::beginTransaction();

            $groupedEvents->map(
                static function (Collection $paymentGroup, string $payTo) {
                    $paymentData = [
                        'events' => $paymentGroup,
                        'account_address' => $payTo,
                        'state' => Payment::STATE_NEW,
                        'completed' => 0,
                    ];

                    $payment = new Payment();
                    $payment->fill($paymentData);
                    $payment->push();
                    $payment->events()->saveMany($paymentGroup);

                    return $payment;
                }
            );

            $licensePayment = new Payment([
                'account_address' => $licenseAccountAddress,
                'state' => Payment::STATE_NEW,
                'completed' => 0,
                'fee' => $totalLicenseFee,
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
        float $demandOperatorFeeCoefficient,
        int &$totalLicenseFee
    ): Collection {
        return $events->each(
            static function (EventLog $entry) use (
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                &$totalLicenseFee
            ) {
                $licenseFee = (int)floor($entry->event_value * $demandLicenseFeeCoefficient);
                $entry->license_fee = $licenseFee;
                $totalLicenseFee += $licenseFee;

                $amountAfterFee = $entry->event_value - $licenseFee;
                $operatorFee = (int)floor($amountAfterFee * $demandOperatorFeeCoefficient);
                $entry->operator_fee = $operatorFee;

                $entry->paid_amount = $amountAfterFee - $operatorFee;
            }
        )->groupBy('pay_to');
    }

    private function processAndGroupConversionsByRecipient(
        \Illuminate\Database\Eloquent\Collection $events,
        float $demandLicenseFeeCoefficient,
        float $demandOperatorFeeCoefficient,
        int &$totalLicenseFee
    ): Collection {
        return $events->each(
            static function (Conversion $entry) use (
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                &$totalLicenseFee
            ) {
                $licenseFee = (int)floor($entry->event_value * $demandLicenseFeeCoefficient);
                $entry->license_fee = $licenseFee;
                $totalLicenseFee += $licenseFee;

                $amountAfterFee = $entry->event_value - $licenseFee;
                $operatorFee = (int)floor($amountAfterFee * $demandOperatorFeeCoefficient);
                $entry->operator_fee = $operatorFee;

                $entry->paid_amount = $amountAfterFee - $operatorFee;
            }
        )->groupBy('pay_to');
    }

    private function getDateTimeFromOption(string $option): ?DateTime
    {
        $value = $this->option($option);

        if (null === $value) {
            return null;
        }

        if (false === ($timestamp = strtotime((string)$value))) {
            throw new InvalidArgumentException(
                sprintf('[DemandPreparePayments] Invalid option %s format "%s"', $option, $value)
            );
        }

        return new DateTime('@'.$timestamp);
    }
}
