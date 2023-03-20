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
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Adserver\Services\Dto\ProcessedPaymentsMetaData;
use Adshares\Adserver\Services\LicenseFeeSender;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Adserver\Services\Supply\OpenRtbBridge;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use stdClass;
use Throwable;

class SupplyProcessPayments extends BaseCommand
{
    private const COLUMN_ADS_PAYMENT_ID = 'ads_payment_id';
    private const COLUMN_BRIDGE_PAYMENT_ID = 'bridge_payment_id';
    private const TRY_OUT_PERIOD_FOR_EVENT_PAYMENT = '-24 hours';

    private const SQL_QUERY_GET_PROCESSED_PAYMENTS_AMOUNT = <<<SQL
SELECT SUM(total_amount) AS total_amount,
       SUM(license_fee)  AS license_fee
FROM network_case_payments
WHERE ads_payment_id = ?
SQL;

    private const SQL_QUERY_SELECT_TIMESTAMPS_TO_UPDATE_TEMPLATE = <<<SQL
SELECT TRUNCATE(UNIX_TIMESTAMP(CONCAT(d, ' ', LPAD(h, 2, '0'), ':00:00')), 0) AS pay_time
FROM
  (
    SELECT DISTINCT DATE(pay_time) AS d, HOUR(pay_time) AS h
    FROM network_case_payments
    WHERE :paymentIdColumn IN (:whereInPlaceholder)
    UNION
    SELECT DISTINCT DATE(created_at) AS d, HOUR(created_at) AS h
    FROM network_cases
    WHERE id IN (
      SELECT DISTINCT network_case_id FROM network_case_payments WHERE :paymentIdColumn IN (:whereInPlaceholder)
    )
  ) t;
SQL;

    protected $signature = 'ops:supply:payments:process {--c|chunkSize=5000}';
    protected $description = 'Processes payments for events';

    public function __construct(
        Locker $locker,
        private readonly AdsClient $adsClient,
        private readonly DemandClient $demandClient,
        private readonly LicenseReader $licenseReader,
        private readonly PaymentDetailsProcessor $paymentDetailsProcessor,
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

        $processedAdsPaymentMetaData = $this->processAdsPayments();
        $processedBridgePaymentMetaData = (new OpenRtbBridge())->processPayments(
            $this->demandClient,
            $this->paymentDetailsProcessor,
            (int)$this->option('chunkSize'),
        );
        $processedPaymentsForAds = $processedAdsPaymentMetaData->getProcessedPaymentsForAds()
            + $processedBridgePaymentMetaData->getProcessedPaymentsForAds();
        $processedPaymentsTotal = $processedAdsPaymentMetaData->getProcessedPaymentsTotal()
            + $processedBridgePaymentMetaData->getProcessedPaymentsTotal();

        $this->invalidateUpdatedCasesStatistics($processedAdsPaymentMetaData, $processedBridgePaymentMetaData);
        ServerEvent::dispatch(ServerEventType::IncomingAdPaymentProcessed, [
            'adsPaymentCount' => $processedPaymentsForAds,
            'totalPaymentCount' => $processedPaymentsTotal,
        ]);

        $this->info('End command ' . $this->getName());
    }

    private function processAdsPayments(): ProcessedPaymentsMetaData
    {
        $processedAdsPaymentIds = [];
        $processedPaymentsTotal = 0;
        $processedPaymentsForAds = 0;

        $adsPayments = AdsPayment::fetchByStatus(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE);
        $earliestTryOutDateTime = new DateTimeImmutable(self::TRY_OUT_PERIOD_FOR_EVENT_PAYMENT);

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
                        'Error during handling paid events for ads payment id=%d (%s)',
                        $adsPayment->id,
                        $throwable->getMessage()
                    )
                );
            }
        }
        return new ProcessedPaymentsMetaData(
            $processedAdsPaymentIds,
            $processedPaymentsTotal,
            $processedPaymentsForAds,
        );
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
            $paymentSum = DB::select(self::SQL_QUERY_GET_PROCESSED_PAYMENTS_AMOUNT, [$incomingPayment->id]);
            if (!empty($paymentSum)) {
                $sum = $paymentSum[0];
                $resultsCollection->add(new PaymentProcessingResult($sum->total_amount, $sum->license_fee));
            }
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

    private function invalidateUpdatedCasesStatistics(
        ProcessedPaymentsMetaData $processedAdsPaymentMetaData,
        ProcessedPaymentsMetaData $processedBridgePaymentMetaData,
    ): void {
        $timestamps = array_unique(
            array_merge(
                $this->fetchTimestampsToUpdate(
                    $processedAdsPaymentMetaData->getPaymentIds(),
                    self::COLUMN_ADS_PAYMENT_ID,
                ),
                $this->fetchTimestampsToUpdate(
                    $processedBridgePaymentMetaData->getPaymentIds(),
                    self::COLUMN_BRIDGE_PAYMENT_ID,
                ),
            ),
        );
        foreach ($timestamps as $timestamp) {
            NetworkCaseLogsHourlyMeta::invalidate($timestamp);
        }
    }

    private function fetchTimestampsToUpdate(array $paymentIds, string $paymentIdColumn): array
    {
        if (empty($paymentIds)) {
            return [];
        }

        $whereInPlaceholder = str_repeat('?,', count($paymentIds) - 1) . '?';
        $query = str_replace(
            [':paymentIdColumn', ':whereInPlaceholder'],
            [$paymentIdColumn, $whereInPlaceholder],
            self::SQL_QUERY_SELECT_TIMESTAMPS_TO_UPDATE_TEMPLATE,
        );

        return array_map(
            fn(stdClass $item) => (int)$item->pay_time,
            DB::select($query, array_merge($paymentIds, $paymentIds))
        );
    }
}
