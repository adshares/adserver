<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Services\PaymentProcessingResult;
use Exception;
use Illuminate\Support\Facades\Log;
use function sprintf;

final class ResultsCollection
{
    public function __construct()
    {
    }

    public function add(PaymentProcessingResult $processPaymentDetails): void
    {
    }

    public function lastSum(): int
    {
    }

    public function sendLicencePayments(): void
    {
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
}
