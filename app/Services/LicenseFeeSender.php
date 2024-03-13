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

namespace Adshares\Adserver\Services;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Exception;
use Illuminate\Support\Facades\Log;

use function array_reduce;
use function config;
use function sprintf;

final class LicenseFeeSender
{
    /** @var PaymentProcessingResult[] */
    private array $results = [];

    public function __construct(
        private readonly AdsClient $adsClient,
        private readonly LicenseReader $licenseReader,
        private readonly AdsPayment $adsPayment,
    ) {
    }

    public function add(PaymentProcessingResult $processPaymentDetails): void
    {
        $this->results[] = $processPaymentDetails;
    }

    public function eventValueSum(): int
    {
        return array_reduce(
            $this->results,
            static function (int $carry, PaymentProcessingResult $result) {
                return $carry + $result->eventValuePartialSum();
            },
            0
        );
    }

    public function licenseFeeSum(): int
    {
        return array_reduce(
            $this->results,
            static function (int $carry, PaymentProcessingResult $result) {
                return $carry + $result->licenseFeePartialSum();
            },
            0
        );
    }

    public function operatorFeeSum(): int
    {
        return array_reduce(
            $this->results,
            static function (int $carry, PaymentProcessingResult $result) {
                return $carry + $result->operatorFeePartialSum();
            },
            0
        );
    }

    public function sendAllLicensePayments(): ?NetworkPayment
    {
        $receiverAddress = $this->licenseAddress();
        if (null === $receiverAddress) {
            return null;
        }

        $payment = NetworkPayment::registerNetworkPayment(
            $receiverAddress,
            config('app.adshares_address'),
            $this->licenseFeeSum(),
            $this->adsPayment
        );
        $this->sendSingleLicensePayment($payment);

        return $payment;
    }

    private function sendSingleLicensePayment(NetworkPayment $payment): void
    {
        try {
            if ($payment->amount > 0) {
                $command = new SendOneCommand($payment->receiver_address, $payment->amount);
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

            Log::error(sprintf(
                $message,
                $exceptionMessage,
                $payment->receiver_address,
                $payment->id,
                $payment->amount
            ));
        }
    }

    public function licenseAddress(): ?string
    {
        return $this->licenseReader->getAddress()?->toString();
    }
}
