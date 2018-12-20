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

use Adshares\Adserver\Exceptions\InvalidPaymentDetailsException;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Supply\Application\Service\AdSelectEventExporter;

class PaymentDetailsProcessor
{
    /** @var AdSelectEventExporter $adSelectEventExporter */
    private $adSelectEventExporter;

    private $adServerAddress;

    public function __construct(AdSelectEventExporter $adSelectEventExporter)
    {
        $this->adSelectEventExporter = $adSelectEventExporter;
        $this->adServerAddress = config('app.adshares_address');
    }

    /**
     * @param string $senderAddress
     * @param int $paymentId
     * @param array $paymentDetails
     *
     * @throws InvalidPaymentDetailsException
     */
    public function processPaymentDetails(string $senderAddress, int $paymentId, array $paymentDetails): void
    {
        $this->validatePayment($paymentId, $paymentDetails);

        $splitPayments = [];
        $dateFrom = null;

        foreach ($paymentDetails as $paymentDetail) {
            $event = NetworkEventLog::fetchByEventId($paymentDetail['event_id']);

            if ($event === null) {
                // TODO log null $event - it means that Demand Server sent event which cannot be found in Supply DB
                continue;
            }

            $event->pay_from = $senderAddress;
            $event->payment_id = $paymentId;
            $event->event_value = $paymentDetail['event_value'];
            $event->paid_amount = $paymentDetail['event_value'];

            $event->save();

            if ($dateFrom === null) {
                $dateFrom = $event->updated_at;
            }

            $publisherId = $event->publisher_id;
            $amount = $splitPayments[$publisherId] ?? 0;
            $amount += $paymentDetail['event_value'];
            $splitPayments[$publisherId] = $amount;
        }

        foreach ($splitPayments as $userUuid => $amount) {
            $user = User::fetchByUuid($userUuid);

            if ($user === null) {
                // TODO log null $user - it means that in Supply DB is event with incorrect publisher_id
                continue;
            }

            $ul = new UserLedgerEntry();
            $ul->user_id = $user->id;
            $ul->amount = $amount;
            $ul->address_from = $senderAddress;
            $ul->address_to = $this->adServerAddress;
            $ul->status = UserLedgerEntry::STATUS_ACCEPTED;
            $ul->type = UserLedgerEntry::TYPE_AD_INCOME;
            $ul->save();
        }

        if ($dateFrom !== null) {
            $this->adSelectEventExporter->exportPayments($dateFrom);
        }
    }

    private function validatePayment(int $paymentId, array $paymentDetails): void
    {
        $adsPayment = AdsPayment::find($paymentId);
        $amountReceived = (int)$adsPayment->amount;

        $amountToPay = 0;
        foreach ($paymentDetails as $paymentDetail) {
            $amountToPay += $paymentDetail['event_value'];
        }

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
}
