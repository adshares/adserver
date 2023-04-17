<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\EventLogsHourlyMeta;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Infrastructure\Service\CommunityFeeReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemandPreparePayments extends BaseCommand
{
    public const COMMAND_SIGNATURE = 'ops:demand:payments:prepare';

    protected $signature = self::COMMAND_SIGNATURE . '
                            {--c|chunkSize=10000}
                            {--f|from= : Date from which unpaid events will be searched}
                            {--t|to= : Date to which unpaid events will be searched}';

    protected $description = 'Prepares payments for events and license';

    public function __construct(
        Locker $locker,
        private readonly CommunityFeeReader $communityFeeReader,
        private readonly LicenseReader $licenseReader,
    ) {
        parent::__construct($locker);
    }

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info('Command ' . self::COMMAND_SIGNATURE . ' already running');
            return self::FAILURE;
        }

        $this->info('Start command ' . self::COMMAND_SIGNATURE);
        $from = $this->getDateTimeFromOption('from') ?: new DateTime('-24 hour');
        $to = $this->getDateTimeFromOption('to');
        if ($from !== null && $to !== null && $to < $from) {
            $this->release();
            throw new InvalidArgumentException(
                sprintf(
                    '[DemandPreparePayments] Invalid period from (%s) to (%s)',
                    $from->format(DateTimeInterface::ATOM),
                    $to->format(DateTimeInterface::ATOM)
                )
            );
        }

        $licenseAccountAddress = $this->licenseReader->getAddress()?->toString();
        $demandLicenseFeeCoefficient = $this->licenseReader->getFee(LicenseReader::LICENSE_TX_FEE);
        $demandOperatorFeeCoefficient = config('app.payment_tx_fee');
        $communityAccountAddress = $this->communityFeeReader->getAddress()->toString();
        $communityFeeCoefficient = $this->communityFeeReader->getFee();

        $conversions = Conversion::fetchUnpaidConversions($from, $to);
        $conversionCount = count($conversions);
        $this->info("Found $conversionCount payable conversions.");
        $hourTimestamp = DateUtils::getDateTimeRoundedToCurrentHour();
        if ($conversionCount > 0) {
            $totalEventValue = 0;
            $totalLicenseFee = 0;
            $totalOperatorFee = 0;
            $totalCommunityFee = 0;
            $eventLogIds = [];
            $groupedConversions = $this->processAndGroupEventsByRecipient(
                $conversions,
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                $communityFeeCoefficient,
                $totalEventValue,
                $totalLicenseFee,
                $totalOperatorFee,
                $totalCommunityFee,
                $eventLogIds
            );

            $this->info(sprintf('In that, there are %d recipients', count($groupedConversions)));

            DB::beginTransaction();

            foreach (EventLog::fetchCreationHourTimestampByIds($eventLogIds) as $timestamp) {
                EventLogsHourlyMeta::invalidate($timestamp);
            }

            $this->storeEventValue($totalEventValue, $hourTimestamp);
            $this->storeOperatorFee($totalOperatorFee, $hourTimestamp);

            $groupedConversions->each(
                function (Collection $paymentGroup, string $payTo) use ($hourTimestamp) {
                    $amount = $paymentGroup->sum('paid_amount');
                    $payment = $this->savePayment($payTo, $amount);
                    if ($amount > 0) {
                        TurnoverEntry::increaseOrInsert($hourTimestamp, TurnoverEntryType::DspExpense, $amount, $payTo);
                    }
                    foreach ($paymentGroup as $row) {
                        $row->payment_id = $payment->id;
                        $row->updated_at = new DateTime();
                        $row->save();
                    }
                }
            );

            $this->saveLicensePayment($licenseAccountAddress, $totalLicenseFee, $hourTimestamp);
            $this->saveCommunityPayment($communityAccountAddress, $totalCommunityFee, $hourTimestamp);
            DB::commit();
        }
        while (true) {
            $events = EventLog::fetchUnpaidEvents($from, $to, (int)$this->option('chunkSize'));

            $eventCount = count($events);
            $this->info("Found $eventCount payable events.");

            if (!$eventCount) {
                break;
            }

            $totalEventValue = 0;
            $totalLicenseFee = 0;
            $totalOperatorFee = 0;
            $totalCommunityFee = 0;
            $eventLogIds = [];
            $groupedEvents = $this->processAndGroupEventsByRecipient(
                $events,
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                $communityFeeCoefficient,
                $totalEventValue,
                $totalLicenseFee,
                $totalOperatorFee,
                $totalCommunityFee,
                $eventLogIds
            );

            $this->info(sprintf('In that, there are %d recipients', count($groupedEvents)));

            DB::beginTransaction();

            $this->storeEventValue($totalEventValue, $hourTimestamp);
            $this->storeOperatorFee($totalOperatorFee, $hourTimestamp);

            $groupedEvents->each(
                function (Collection $paymentGroup, string $payTo) use ($hourTimestamp) {
                    $amount = $paymentGroup->sum('paid_amount');
                    $payment = $this->savePayment($payTo, $amount);
                    if ($amount > 0) {
                        TurnoverEntry::increaseOrInsert($hourTimestamp, TurnoverEntryType::DspExpense, $amount, $payTo);
                    }
                    foreach ($paymentGroup as $row) {
                        $row->payment_id = $payment->id;
                        $row->updated_at = new DateTime();
                        $row->save();
                    }
                }
            );

            $this->saveLicensePayment($licenseAccountAddress, $totalLicenseFee, $hourTimestamp);
            $this->saveCommunityPayment($communityAccountAddress, $totalCommunityFee, $hourTimestamp);

            DB::commit();
        }

        $this->invalidateStatisticsForPreparedEvents($from, $to);
        $this->release();
        return self::SUCCESS;
    }

    private function processAndGroupEventsByRecipient(
        Collection $events,
        float $demandLicenseFeeCoefficient,
        float $demandOperatorFeeCoefficient,
        float $communityFeeCoefficient,
        int &$totalEventValue,
        int &$totalLicenseFee,
        int &$totalOperatorFee,
        int &$totalCommunityFee,
        array &$eventLogIds
    ): Collection {
        return $events->each(
            static function ($entry) use (
                $demandLicenseFeeCoefficient,
                $demandOperatorFeeCoefficient,
                $communityFeeCoefficient,
                &$totalEventValue,
                &$totalLicenseFee,
                &$totalOperatorFee,
                &$totalCommunityFee,
                &$eventLogIds
            ) {
                $eventLogIds[] = $entry->event_logs_id;
                $totalEventValue += $entry->event_value;

                $licenseFee = (int)floor($entry->event_value * $demandLicenseFeeCoefficient);
                $entry->license_fee = $licenseFee;
                $totalLicenseFee += $licenseFee;

                $amountAfterFee = $entry->event_value - $licenseFee;
                $operatorFee = (int)floor($amountAfterFee * $demandOperatorFeeCoefficient);
                $entry->operator_fee = $operatorFee;
                $totalOperatorFee += $operatorFee;

                $amountAfterFee -= $operatorFee;
                $communityFee = (int)floor($amountAfterFee * $communityFeeCoefficient);
                $entry->community_fee = $communityFee;
                $totalCommunityFee += $communityFee;

                $entry->paid_amount = $amountAfterFee - $communityFee;
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

        return new DateTime('@' . $timestamp);
    }

    private function invalidateStatisticsForPreparedEvents(DateTimeInterface $from, ?DateTimeInterface $to): void
    {
        $timestamp = DateUtils::roundTimestampToHour($from->getTimestamp());
        $toTimestamp = null !== $to ? $to->getTimestamp() : time();

        while ($timestamp < $toTimestamp) {
            EventLogsHourlyMeta::invalidate($timestamp);
            $timestamp += DateUtils::HOUR;
        }
    }

    private function storeEventValue(int $totalEventValue, DateTime $hourTimestamp): void
    {
        if ($totalEventValue > 0) {
            TurnoverEntry::increaseOrInsert(
                $hourTimestamp,
                TurnoverEntryType::DspAdvertisersExpense,
                $totalEventValue,
            );
        }
    }

    private function storeOperatorFee(int $totalOperatorFee, DateTime $hourTimestamp): void
    {
        if ($totalOperatorFee > 0) {
            TurnoverEntry::increaseOrInsert($hourTimestamp, TurnoverEntryType::DspOperatorFee, $totalOperatorFee);
        }
    }

    private function savePayment(string $accountAddress, int $fee): Payment
    {
        $payment = new Payment([
            'account_address' => $accountAddress,
            'state' => Payment::STATE_NEW,
            'completed' => 0,
            'fee' => $fee,
        ]);
        $payment->push();
        return $payment;
    }

    private function saveLicensePayment(
        ?string $licenseAccountAddress,
        int $totalLicenseFee,
        DateTimeInterface $hourTimestamp,
    ): void {
        if (null !== $licenseAccountAddress) {
            $licensePayment = $this->savePayment($licenseAccountAddress, $totalLicenseFee);
            $this->info(
                sprintf(
                    'and a license fee of %d clicks payable to %s',
                    $licensePayment->fee,
                    $licensePayment->account_address,
                )
            );
            if ($totalLicenseFee > 0) {
                TurnoverEntry::increaseOrInsert(
                    $hourTimestamp,
                    TurnoverEntryType::DspLicenseFee,
                    $totalLicenseFee,
                    $licenseAccountAddress,
                );
            }
        }
    }

    private function saveCommunityPayment(
        string $communityAccountAddress,
        int $totalCommunityFee,
        DateTimeInterface $hourTimestamp,
    ): void {
        $communityPayment = $this->savePayment($communityAccountAddress, $totalCommunityFee);
        $this->info(
            sprintf(
                'and a community fee of %d clicks payable to %s',
                $communityPayment->fee,
                $communityPayment->account_address,
            )
        );
        if ($totalCommunityFee > 0) {
            TurnoverEntry::increaseOrInsert($hourTimestamp, TurnoverEntryType::DspCommunityFee, $totalCommunityFee);
        }
    }
}
