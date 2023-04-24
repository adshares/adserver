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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Adserver\Services\LicenseFeeSender;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use stdClass;
use Throwable;

class SupplyProcessPayments extends BaseCommand
{
    private const TRY_OUT_PERIOD_FOR_EVENT_PAYMENT = '-24 hours';

    private const SQL_QUERY_GET_PROCESSED_PAYMENTS_AMOUNT = <<<SQL
SELECT IFNULL(SUM(total_amount), 0) AS total_amount,
       IFNULL(SUM(license_fee), 0)  AS license_fee,
       IFNULL(SUM(operator_fee), 0) AS operator_fee
FROM network_case_payments
WHERE ads_payment_id = ?
SQL;

    private const SQL_QUERY_SELECT_TIMESTAMPS_TO_UPDATE_TEMPLATE = <<<SQL
SELECT TRUNCATE(UNIX_TIMESTAMP(CONCAT(d, ' ', LPAD(h, 2, '0'), ':00:00')), 0) AS pay_time
FROM
  (
    SELECT DISTINCT DATE(pay_time) AS d, HOUR(pay_time) AS h
    FROM network_case_payments
    WHERE ads_payment_id IN (%s)
    UNION
    SELECT DISTINCT DATE(created_at) AS d, HOUR(created_at) AS h
    FROM network_cases
    WHERE id IN (SELECT DISTINCT network_case_id FROM network_case_payments WHERE ads_payment_id IN (%s))) t;
SQL;

    protected $signature = 'ops:supply:payments:process {--c|chunkSize=5000}';
    protected $description = 'Processes payments for events';

    public function __construct(
        Locker $locker,
        private readonly AdsClient $adsClient,
        private readonly DemandClient $demandClient,
        private readonly LicenseReader $licenseReader,
        private readonly PaymentDetailsProcessor $paymentDetailsProcessor
    ) {
        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->getName() . ' already running');
            return;
        }

        $this->info('Start command ' . $this->getName());

        $adsPayments = AdsPayment::fetchByStatus(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE);

        $earliestTryOutDateTime = new DateTimeImmutable(self::TRY_OUT_PERIOD_FOR_EVENT_PAYMENT);
        $processedAdsPaymentIds = [];
        $processedPaymentsTotal = 0;
        $processedPaymentsForAds = 0;

        /** @var AdsPayment $adsPayment */
        foreach ($adsPayments as $adsPayment) {
            if ($adsPayment->created_at < $earliestTryOutDateTime) {
                $adsPayment->status = AdsPayment::STATUS_RESERVED;
                $adsPayment->save();
                ++$processedPaymentsTotal;
                continue;
            }

            DB::beginTransaction();
            try {
                $this->handleEventPaymentCandidate($adsPayment);
                $adsPayment->save();

                DB::commit();

                if (AdsPayment::STATUS_EVENT_PAYMENT === $adsPayment->status) {
                    ++$processedPaymentsForAds;
                }
                ++$processedPaymentsTotal;
                $processedAdsPaymentIds[] = $adsPayment->id;
            } catch (Throwable $throwable) {
                DB::rollBack();
                $this->error(
                    sprintf(
                        'Error during handling paid events for id=%d (%s)',
                        $adsPayment->id,
                        $throwable->getMessage()
                    )
                );
            }
        }

        $timestamps = $this->fetchTimestampsToUpdate($processedAdsPaymentIds);
        foreach ($timestamps as $timestamp) {
            NetworkCaseLogsHourlyMeta::invalidate($timestamp);
        }
        ServerEvent::dispatch(ServerEventType::IncomingAdPaymentProcessed, [
            'adsPaymentCount' => $processedPaymentsForAds,
            'totalPaymentCount' => $processedPaymentsTotal,
        ]);

        $this->info('End command ' . $this->getName());
    }

    private function handleEventPaymentCandidate(AdsPayment $incomingPayment): void
    {
        if (null === ($networkHost = NetworkHost::fetchByAddress($incomingPayment->address))) {
            return;
        }

        $resultsCollection = new LicenseFeeSender($this->adsClient, $this->licenseReader, $incomingPayment);

        $limit = (int)$this->option('chunkSize');
        $offset = $incomingPayment->last_offset ?? 0;
        if ($offset > 0) {
            $sum = DB::selectOne(self::SQL_QUERY_GET_PROCESSED_PAYMENTS_AMOUNT, [$incomingPayment->id]);
            $resultsCollection->add(
                new PaymentProcessingResult($sum->total_amount, $sum->license_fee, $sum->operator_fee)
            );
        }
        $transactionTime = $incomingPayment->tx_time;

        for ($paymentDetailsSize = $limit; $paymentDetailsSize === $limit;) {
            try {
                $paymentDetails = $this->demandClient->fetchPaymentDetails(
                    $networkHost->host,
                    $incomingPayment->txid,
                    $limit,
                    $offset
                );
                $paymentDetailsSize = count($paymentDetails);
            } catch (EmptyInventoryException | UnexpectedClientResponseException) {
                return;
            }

            $processPaymentDetails = $this->paymentDetailsProcessor->processPaidEvents(
                $incomingPayment,
                $transactionTime,
                $paymentDetails,
                $resultsCollection->eventValueSum()
            );

            $resultsCollection->add($processPaymentDetails);

            $incomingPayment->last_offset = $offset += $limit;
        }

        $this->storeTurnoverEntries($resultsCollection, $incomingPayment);
        $this->paymentDetailsProcessor->addAdIncomeToUserLedger($incomingPayment);

        $incomingPayment->status = AdsPayment::STATUS_EVENT_PAYMENT;

        $licensePayment = $resultsCollection->sendAllLicensePayments();
        if (null === $licensePayment) {
            $this->info('No license payment');
        } else {
            $this->info(
                sprintf(
                    'License payment TX_ID: %s. Sent %d to %s.',
                    $licensePayment->tx_id,
                    $licensePayment->amount,
                    $licensePayment->receiver_address
                )
            );
        }
    }

    private function fetchTimestampsToUpdate(array $adsPaymentIds): array
    {
        if (empty($adsPaymentIds)) {
            return [];
        }

        $whereInPlaceholder = str_repeat('?,', count($adsPaymentIds) - 1) . '?';
        $query = sprintf(
            self::SQL_QUERY_SELECT_TIMESTAMPS_TO_UPDATE_TEMPLATE,
            $whereInPlaceholder,
            $whereInPlaceholder
        );

        return array_map(
            function (stdClass $item) {
                return (int)$item->pay_time;
            },
            DB::select($query, array_merge($adsPaymentIds, $adsPaymentIds))
        );
    }

    private function storeTurnoverEntries(LicenseFeeSender $resultsCollection, AdsPayment $incomingPayment): void
    {
        $hourTimestamp = DateUtils::getDateTimeRoundedToCurrentHour();

        $totalEventValue = $resultsCollection->eventValueSum();
        if ($totalEventValue <= 0) {
            return;
        }
        TurnoverEntry::increaseOrInsert(
            $hourTimestamp,
            TurnoverEntryType::SspIncome,
            $totalEventValue,
            $incomingPayment->address,
        );

        $totalLicenseFee = $resultsCollection->licenseFeeSum();
        if ($totalLicenseFee > 0 && null !== ($licenseAddress = $resultsCollection->licenseAddress())) {
            TurnoverEntry::increaseOrInsert(
                $hourTimestamp,
                TurnoverEntryType::SspLicenseFee,
                $totalLicenseFee,
                $licenseAddress,
            );
        }

        $totalOperatorFeeSum = $resultsCollection->operatorFeeSum();
        if ($totalOperatorFeeSum > 0) {
            TurnoverEntry::increaseOrInsert($hourTimestamp, TurnoverEntryType::SspOperatorFee, $totalOperatorFeeSum);
        }

        $totalPublisherIncome = $totalEventValue - $totalLicenseFee - $totalOperatorFeeSum;
        if ($totalPublisherIncome > 0) {
            TurnoverEntry::increaseOrInsert(
                $hourTimestamp,
                TurnoverEntryType::SspPublishersIncome,
                $totalPublisherIncome,
            );
        }
    }
}
