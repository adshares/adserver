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
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use function max;
use function min;

class PaymentDetailsProcessor
{
    /** @var string */
    private $adServerAddress;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(
        ExchangeRateReader $exchangeRateReader,
        LicenseReader $licenseReader
    ) {
        $this->adServerAddress = (string)config('app.adshares_address');
        $this->exchangeRateReader = $exchangeRateReader;
        $this->licenseReader = $licenseReader;
    }

    private static function fetchOperatorFee(): float
    {
        $operatorFee = Config::fetchFloatOrFail(Config::OPERATOR_RX_FEE);
        if ($operatorFee === null) {
            throw new MissingInitialConfigurationException('No config entry for operator fee.');
        }

        return $operatorFee;
    }

    public function processPaymentDetails(
        AdsPayment $adsPayment,
        array $paymentDetails,
        int $carriedEventValueSum
    ): PaymentProcessingResult {
        $senderAddress = $adsPayment->address;
        $adsPaymentId = $adsPayment->id;

        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();

        $paymentDetails = $this->getPaymentDetailsWhichExistInDb($paymentDetails);
        if (count($paymentDetails) === 0) {
            Log::warning('[PaymentDetailsProcessor] None of received events exist in DB');

            return PaymentProcessingResult::zero();
        }

        $feeCalculator = new PaymentDetailsFeeCalculator($this->fetchLicenceFee(), self::fetchOperatorFee());
        foreach ($paymentDetails as $key => $paymentDetail) {
            $calculatedFees = $feeCalculator->calculateFee($paymentDetail['event_value']);
            $paymentDetail['event_value'] = $calculatedFees['event_value'];
            $paymentDetail['license_fee'] = $calculatedFees['license_fee'];
            $paymentDetail['operator_fee'] = $calculatedFees['operator_fee'];
            $paymentDetail['paid_amount'] = $calculatedFees['paid_amount'];

            $paymentDetails[$key] = $paymentDetail;
        }

        $totalLicenceFee = 0;
        $totalEventValue = 0;

        $exchangeRateValue = $exchangeRate->getValue();

        foreach ($paymentDetails as $paymentDetail) {
            $spendableAmount = max(0, $adsPayment->amount - $carriedEventValueSum - $totalEventValue);
            $event = NetworkEventLog::fetchByEventId($paymentDetail['event_id']);
            if ($event === null) {
                continue;
            }

            $event->pay_from = $senderAddress;
            $event->ads_payment_id = $adsPaymentId;
            $event->event_value = min($spendableAmount, $paymentDetail['event_value']);
            $event->license_fee = $paymentDetail['license_fee'];
            $event->operator_fee = $paymentDetail['operator_fee'];
            $event->paid_amount = $paymentDetail['paid_amount'];
            $event->exchange_rate = $exchangeRateValue;
            $event->paid_amount_currency = $exchangeRate->fromClick($paymentDetail['paid_amount']);

            $event->save();

            $totalLicenceFee += $paymentDetail['license_fee'];
            $totalEventValue += $event->event_value;
        }

        $this->addAdIncomeToUserLedger($adsPayment);

        return new PaymentProcessingResult($totalEventValue, $totalLicenceFee);
    }

    private function getPaymentDetailsWhichExistInDb(array $paymentDetails): array
    {
        foreach ($paymentDetails as $key => $paymentDetail) {
            $eventId = $paymentDetail['event_id'];

            if (NetworkEventLog::fetchByEventId($eventId) !== null) {
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

    private function addAdIncomeToUserLedger(AdsPayment $adsPayment): void
    {
        $splitPayments = NetworkEventLog::fetchPaymentsForPublishersByAdsPaymentId($adsPayment->id);

        foreach ($splitPayments as $splitPayment) {
            $userUuid = $splitPayment->publisher_id;
            $amount = (int)$splitPayment->paid_amount;

            $user = User::fetchByUuid($userUuid);

            if (null === $user) {
                Log::error(
                    sprintf(
                        '[Supply] (SupplySendPayments) User %s does not exist. AdsPayment id %s. Amount %s.',
                        $userUuid,
                        $adsPayment->id,
                        $amount
                    )
                );

                continue;
            }

            $userLedgerEntry = UserLedgerEntry::constructWithAddressAndTransaction(
                $user->id,
                $amount,
                UserLedgerEntry::STATUS_ACCEPTED,
                UserLedgerEntry::TYPE_AD_INCOME,
                $adsPayment->address,
                $this->adServerAddress,
                $adsPayment->txid
            );

            $userLedgerEntry->save();
        }
    }

    private function fetchLicenceFee(): float
    {
        try {
            $licenseFee = $this->licenseReader->getFee(Config::LICENCE_RX_FEE);
        } catch (ModelNotFoundException $modelNotFoundException) {
            throw new MissingInitialConfigurationException('No config entry for license fee.');
        }

        return $licenseFee;
    }
}
