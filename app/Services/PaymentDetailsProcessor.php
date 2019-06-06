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

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PaymentDetailsProcessor
{
    /** @var AdsClient */
    private $adsClient;

    /** @var string */
    private $adServerAddress;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(
        AdsClient $adsClient,
        ExchangeRateReader $exchangeRateReader,
        LicenseReader $licenseReader
    ) {
        $this->adsClient = $adsClient;
        $this->adServerAddress = (string)config('app.adshares_address');
        $this->exchangeRateReader = $exchangeRateReader;
        $this->licenseReader = $licenseReader;
    }

    /**
     * @param AdsPayment $adsPayment
     * @param array $paymentDetails
     *
     * @throws MissingInitialConfigurationException
     */
    public function processPaymentDetails(AdsPayment $adsPayment, array $paymentDetails): void
    {
        $senderAddress = $adsPayment->address;
        $adsPaymentId = $adsPayment->id;
        $amountReceived = $adsPayment->amount;

        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();

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
                $event = NetworkEventLog::fetchByEventId($paymentDetail['event_id']);
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

            $licensePayment = NetworkPayment::registerNetworkPayment(
                $licenseAccount,
                $this->adServerAddress,
                $totalLicenceFee,
                $adsPaymentId
            );

            $this->addAdIncomeToUserLedger($adsPayment);

            DB::commit();

            $this->sendLicensePayment($licensePayment);
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
        // TODO log operator income $totalOperatorFee = $amountReceived - $totalPaidAmount - $totalLicenceFee;
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

    private function getPaymentDetailsTotalWeight(array $paymentDetails): int
    {
        $weightSum = 0;
        foreach ($paymentDetails as $paymentDetail) {
            $weightSum += $paymentDetail['event_value'];
        }

        return $weightSum;
    }

    private function sendLicensePayment(NetworkPayment $payment): void
    {
        $amount = $payment->amount;
        $receiverAddress = $payment->receiver_address;

        try {
            if ($amount > 0) {
                $command = new SendOneCommand($receiverAddress, $amount);
                $response = $this->adsClient->runTransaction($command);
                $responseTx = $response->getTx();

                $payment->tx_id = $responseTx->getId();
                $payment->tx_time = $responseTx->getTime()->getTimestamp();
            }

            $payment->processed = '1';
            $payment->save();
        } catch (Exception $exception) {
            if ($exception instanceof CommandException && $exception->getCode() === CommandError::LOW_BALANCE) {
                $exceptionMessage = 'Insufficient funds on Operator Account.';
            } else {
                $exceptionMessage = sprintf('Unexpected Error (%s).', $exception->getMessage());
            }

            $message = '[Supply] (PaymentDetailsProcessor) %s ';
            $message .= 'Could not send a license fee to %s. NetworkPayment id %s. Amount %s.';

            Log::error(sprintf($message, $exceptionMessage, $receiverAddress, $payment->id, $amount));
        }
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
}
