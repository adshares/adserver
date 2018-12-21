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
use Adshares\Adserver\Exceptions\InvalidPaymentDetailsException;
use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Supply\Application\Service\AdSelectEventExporter;

class PaymentDetailsProcessor
{
    /** @var AdsClient $adsClient */
    private $adsClient;

    /** @var AdSelectEventExporter $adSelectEventExporter */
    private $adSelectEventExporter;

    private $adServerAddress;

    public function __construct(AdsClient $adsClient, AdSelectEventExporter $adSelectEventExporter)
    {
        $this->adsClient = $adsClient;
        $this->adSelectEventExporter = $adSelectEventExporter;
        $this->adServerAddress = config('app.adshares_address');
    }

    /**
     * @param string $senderAddress
     * @param int $paymentId
     * @param array $paymentDetails
     *
     * @throws InvalidPaymentDetailsException
     * @throws MissingInitialConfigurationException
     */
    public function processPaymentDetails(string $senderAddress, int $paymentId, array $paymentDetails): void
    {
        $this->validatePayment($paymentId, $paymentDetails);

        $licenceFee = Config::getFee(Config::LICENCE_RX_FEE);
        $operatorFee = Config::getFee(Config::PAYMENT_RX_FEE);

        $transferAmountBeforeLicenseFee = $this->getPaymentAmount($paymentId);
        $licenceFeeAmount = $licenceFee * $transferAmountBeforeLicenseFee;
        $transferAmountBeforeOperatorFee = $transferAmountBeforeLicenseFee - $licenceFeeAmount;
        $operatorFeeAmount = $operatorFee * $transferAmountBeforeOperatorFee;
        $transferAmountAfterFee = $transferAmountBeforeOperatorFee - $operatorFeeAmount;

        $amountToPay = $this->getAmountToPay($paymentDetails);

        $feeFactor = $transferAmountAfterFee / $amountToPay;

        $splitPayments = [];
        $dateFrom = null;
        $totalPaidAmount = 0;

        foreach ($paymentDetails as $paymentDetail) {
            $event = NetworkEventLog::fetchByEventId($paymentDetail['event_id']);

            if ($event === null) {
                // TODO log null $event - it means that Demand Server sent event which cannot be found in Supply DB
                continue;
            }

            $eventValue = $paymentDetail['event_value'];
            $paidAmount = (int)floor($eventValue * $feeFactor);

            $event->pay_from = $senderAddress;
            $event->payment_id = $paymentId;
            $event->event_value = $eventValue;
            $event->paid_amount = $paidAmount;

            $event->save();

            if ($dateFrom === null) {
                $dateFrom = $event->updated_at;
            }

            $publisherId = $event->publisher_id;
            $amount = $splitPayments[$publisherId] ?? 0;
            $amount += $paidAmount;
            $totalPaidAmount += $paidAmount;
            $splitPayments[$publisherId] = $amount;
        }

        $licenceFeeAmount = (int)floor($licenceFeeAmount);
        $this->sendLicenceTransfer($licenceFeeAmount);

        // TODO log operator income $operatorFeeAmount
        $operatorFeeAmount = $transferAmountBeforeLicenseFee - $licenceFeeAmount - $totalPaidAmount;

        $this->updateUserLedgerWithAdIncome($senderAddress, $splitPayments);

        if ($dateFrom !== null) {
            $this->adSelectEventExporter->exportPayments($dateFrom);
        }
    }

    private function validatePayment(int $paymentId, array $paymentDetails): void
    {
        $amountReceived = $this->getPaymentAmount($paymentId);
        $amountToPay = $this->getAmountToPay($paymentDetails);

        if ($amountReceived < $amountToPay) {
            throw new InvalidPaymentDetailsException(
                sprintf(
                    'Received %d, but the ordered payment is %d clicks.',
                    $amountReceived,
                    $amountToPay
                )
            );
        }
    }

    private function getPaymentAmount(int $adsPaymentId): int
    {
        $adsPayment = AdsPayment::find($adsPaymentId);

        return (int)$adsPayment->amount;
    }

    private function getAmountToPay(array $paymentDetails): int
    {
        $amountToPay = 0;
        foreach ($paymentDetails as $paymentDetail) {
            $amountToPay += $paymentDetail['event_value'];
        }

        return $amountToPay;
    }

    private function sendLicenceTransfer(int $amount): void
    {
        $config = Config::where('key', Config::LICENCE_ACCOUNT)->first();
        if (!$config) {
            throw new MissingInitialConfigurationException(
                sprintf('No config entry for key: %s.', Config::LICENCE_ACCOUNT)
            );
        }
        $address = $config->value;

        // TODO move to queue or Ads service
        $command = new SendOneCommand($address, $amount);
        $response = $this->adsClient->runTransaction($command);
        // TODO log transaction
//        $txid = $response->getTx()->getId();
    }

    private function updateUserLedgerWithAdIncome(string $transferSenderAddress, array $splitPayments): void
    {
        foreach ($splitPayments as $userUuid => $amount) {
            $user = User::fetchByUuid($userUuid);

            if ($user === null) {
                // TODO log null $user - it means that in Supply DB is event with incorrect publisher_id
                continue;
            }

            $ul = new UserLedgerEntry();
            $ul->user_id = $user->id;
            $ul->amount = $amount;
            $ul->address_from = $transferSenderAddress;
            $ul->address_to = $this->adServerAddress;
            $ul->status = UserLedgerEntry::STATUS_ACCEPTED;
            $ul->type = UserLedgerEntry::TYPE_AD_INCOME;
            $ul->save();
        }
    }
}
