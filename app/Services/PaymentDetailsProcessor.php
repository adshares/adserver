<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

namespace Adshares\Adserver\Services;

use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PaymentDetailsProcessor
{
    /** @var string */
    private $adServerAddress;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(ExchangeRateReader $exchangeRateReader, LicenseReader $licenseReader)
    {
        $this->adServerAddress = (string)config('app.adshares_address');
        $this->exchangeRateReader = $exchangeRateReader;
        $this->licenseReader = $licenseReader;
    }

    /**
     * @param string $senderAddress
     * @param int $adsPaymentId
     * @param array $paymentDetails
     *
     * @throws MissingInitialConfigurationException
     */
    public function processPaymentDetails(string $senderAddress, int $adsPaymentId, array $paymentDetails): void
    {
        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate(new DateTime());

        $amountReceived = $this->getPaymentAmount($adsPaymentId);

        try {
            $licenseAccount = $this->licenseReader->getAddress()->toString();
        } catch (ModelNotFoundException $modelNotFoundException) {
            throw new MissingInitialConfigurationException('No config entry for license account.');
        }

        try {
            $licenseFee = $this->licenseReader->getFee(Config::LICENCE_RX_FEE);
        } catch (ModelNotFoundException $modelNotFoundException) {
            throw new MissingInitialConfigurationException('No config entry for license fee.');
        }

        $operatorFee = Config::fetchFloatOrFail(Config::OPERATOR_RX_FEE);
        if ($operatorFee === null) {
            throw new MissingInitialConfigurationException('No config entry for operator fee.');
        }

        $paymentDetails = $this->getPaymentDetailsWhichExistInDb($paymentDetails);
        if (count($paymentDetails) === 0) {
            Log::warning('[PaymentDetailsProcessor] None of received events exist in DB');

            return;
        }

        $totalWeight = $this->getPaymentDetailsTotalWeight($paymentDetails);
        $feeCalculator = new PaymentDetailsFeeCalculator($amountReceived, $totalWeight, $licenseFee, $operatorFee);
        foreach ($paymentDetails as $key => $paymentDetail) {
            $calculatedFees = $feeCalculator->calculateFee($paymentDetail['event_value']);
            $paymentDetail['event_value'] = $calculatedFees['event_value'];
            $paymentDetail['license_fee'] = $calculatedFees['license_fee'];
            $paymentDetail['operator_fee'] = $calculatedFees['operator_fee'];
            $paymentDetail['paid_amount'] = $calculatedFees['paid_amount'];

            $paymentDetails[$key] = $paymentDetail;
        }

        $totalPaidAmount = 0;
        $totalLicenceFee = 0;

        $exchangeRateValue = $exchangeRate->getValue();

        try {
            DB::beginTransaction();

            foreach ($paymentDetails as $paymentDetail) {
                $event = $this->getEventById($paymentDetail['event_id']);
                if ($event === null) {
                    continue;
                }

                $event->pay_from = $senderAddress;
                $event->ads_payment_id = $adsPaymentId;
                $event->event_value = $paymentDetail['event_value'];
                $event->license_fee = $paymentDetail['license_fee'];
                $event->operator_fee = $paymentDetail['operator_fee'];
                $event->paid_amount = $paymentDetail['paid_amount'];
                $event->exchange_rate = $exchangeRateValue;
                $event->paid_amount_currency = $exchangeRate->fromClick($paymentDetail['paid_amount']);

                $event->save();

                $totalPaidAmount += $paymentDetail['paid_amount'];
                $totalLicenceFee += $paymentDetail['license_fee'];
            }

            NetworkPayment::registerNetworkPayment(
                $licenseAccount,
                $this->adServerAddress,
                $totalLicenceFee,
                $adsPaymentId
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
        // TODO log operator income $totalOperatorFee = $amountReceived - $totalPaidAmount - $totalLicenceFee;
    }

    private function getPaymentAmount(int $adsPaymentId): int
    {
        $adsPayment = AdsPayment::find($adsPaymentId);

        return (int)$adsPayment->amount;
    }

    private function getPaymentDetailsWhichExistInDb(array $paymentDetails): array
    {
        foreach ($paymentDetails as $key => $paymentDetail) {
            $eventId = $paymentDetail['event_id'];

            if ($this->getEventById($eventId) !== null) {
                continue;
            }

            Log::warning(
                sprintf(
                    '[PaymentDetailsProcessor] Demand Server sent event_id (%s) which cannot be found in Supply DB',
                    $eventId
                )
            );

            unset($paymentDetails[$key]);
        }

        return $paymentDetails;
    }

    private function getEventById(string $eventId): ?NetworkEventLog
    {
        $event = NetworkEventLog::fetchByEventId($eventId);
        if ($event !== null) {
            return $event;
        }

        $lastOccurrence = strrpos($eventId, ':');
        if ($lastOccurrence !== false) {
            $caseId = substr($eventId, 0, $lastOccurrence);

            $event = NetworkEventLog::fetchByCaseId($caseId)->first();

            if ($event !== null) {
                return $event;
            }
        }

        return null;
    }

    private function getPaymentDetailsTotalWeight(array $paymentDetails): int
    {
        $weightSum = 0;
        foreach ($paymentDetails as $paymentDetail) {
            $weightSum += $paymentDetail['event_value'];
        }

        return $weightSum;
    }
}
