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

declare(strict_types=1);

namespace Adshares\Adserver\Services;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

use function array_reduce;
use function config;
use function sprintf;

final class LicenseFeeSender
{
    /** @var AdsClient */
    private $adsClient;

    /** @var PaymentProcessingResult[] */
    private $results = [];

    /** @var LicenseReader */
    private $licenseReader;

    /** @var AdsPayment */
    private $adsPayment;

    public function __construct(AdsClient $adsClient, LicenseReader $licenseReader, AdsPayment $adsPayment)
    {
        $this->adsClient = $adsClient;
        $this->licenseReader = $licenseReader;
        $this->adsPayment = $adsPayment;
    }

    public function add(PaymentProcessingResult $processPaymentDetails): void
    {
        $this->results[] = $processPaymentDetails;
    }

    public function eventValueSum(): int
    {
        return (int)array_reduce(
            $this->results,
            static function (int $carry, PaymentProcessingResult $result) {
                return $carry + $result->eventValuePartialSum();
            },
            0
        );
    }

    public function licenseFeeSum(): int
    {
        return (int)array_reduce(
            $this->results,
            static function (int $carry, PaymentProcessingResult $result) {
                return $carry + $result->licenseFeePartialSum();
            },
            0
        );
    }

    public function sendAllLicensePayments(): NetworkPayment
    {
        $payment = NetworkPayment::registerNetworkPayment(
            $this->fetchLicenseAccount(),
            (string)config('app.adshares_address'),
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

    private function fetchLicenseAccount(): string
    {
        try {
            $licenseAccount = $this->licenseReader->getAddress()->toString();
        } catch (ModelNotFoundException $modelNotFoundException) {
            throw new MissingInitialConfigurationException('No config entry for license account.');
        }

        return $licenseAccount;
    }
}
