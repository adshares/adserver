<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Adserver\Services\LicenseFeeSender;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use stdClass;
use Throwable;

use function sprintf;

class SupplyProcessPayments extends BaseCommand
{
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
    WHERE ads_payment_id IN (%s)
    UNION
    SELECT DISTINCT DATE(created_at) AS d, HOUR(created_at) AS h
    FROM network_cases
    WHERE id IN (SELECT DISTINCT network_case_id FROM network_case_payments WHERE ads_payment_id IN (%s))) t;
SQL;

    protected $signature = 'ops:supply:payments:process {--c|chunkSize=5000}';

    protected $description = 'Processes payments for events';

    /** @var AdsClient */
    private $adsClient;

    /** @var DemandClient $demandClient */
    private $demandClient;

    /** @var LicenseReader */
    private $licenseReader;

    /** @var PaymentDetailsProcessor */
    private $paymentDetailsProcessor;

    public function __construct(
        Locker $locker,
        AdsClient $adsClient,
        DemandClient $demandClient,
        LicenseReader $licenseReader,
        PaymentDetailsProcessor $paymentDetailsProcessor
    ) {
        parent::__construct($locker);

        $this->adsClient = $adsClient;
        $this->demandClient = $demandClient;
        $this->licenseReader = $licenseReader;
        $this->paymentDetailsProcessor = $paymentDetailsProcessor;
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

        /** @var AdsPayment $adsPayment */
        foreach ($adsPayments as $adsPayment) {
            if ($adsPayment->created_at < $earliestTryOutDateTime) {
                $adsPayment->status = AdsPayment::STATUS_RESERVED;
                $adsPayment->save();

                continue;
            }

            DB::beginTransaction();
            try {
                $this->handleEventPaymentCandidate($adsPayment);
                $adsPayment->save();

                DB::commit();

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
            } catch (EmptyInventoryException | UnexpectedClientResponseException $clientException) {
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
        $this->info(
            sprintf(
                'LicensePayment TX_ID: %s. Sent %d to %s.',
                $licensePayment->tx_id,
                $licensePayment->amount,
                $licensePayment->receiver_address
            )
        );
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
}
