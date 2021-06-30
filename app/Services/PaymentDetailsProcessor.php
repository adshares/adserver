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

use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\ConfigException;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use DateTime;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use function max;
use function min;
use function sprintf;

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
        try {
            $operatorFee = Config::fetchFloatOrFail(Config::OPERATOR_RX_FEE);
        } catch (ConfigException $exception) {
            throw new MissingInitialConfigurationException('No config entry for operator fee.');
        }

        return $operatorFee;
    }

    public function processPaidEvents(
        AdsPayment $adsPayment,
        DateTime $transactionTime,
        array $paymentDetails,
        int $carriedEventValueSum
    ): PaymentProcessingResult {
        $adsPaymentId = $adsPayment->id;

        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();
        $feeCalculator = new PaymentDetailsFeeCalculator($this->fetchLicenseFee(), self::fetchOperatorFee());
        $totalLicenseFee = 0;
        $totalEventValue = 0;

        $exchangeRateValue = $exchangeRate->getValue();

        $cases = $this->fetchNetworkCasesForPaymentDetails($paymentDetails);

        foreach ($paymentDetails as $paymentDetail) {
            /** @var NetworkCase $case */
            if (null === ($case = $cases->get($paymentDetail['case_id']))) {
                continue;
            }

            $spendableAmount = max(0, $adsPayment->amount - $carriedEventValueSum - $totalEventValue);
            $eventValue = min($spendableAmount, $paymentDetail['event_value']);
            $calculatedFees = $feeCalculator->calculateFee($eventValue);

            $networkCasePayment = NetworkCasePayment::create(
                $transactionTime,
                $adsPaymentId,
                $eventValue,
                $calculatedFees['license_fee'],
                $calculatedFees['operator_fee'],
                $calculatedFees['paid_amount'],
                $exchangeRateValue,
                $exchangeRate->fromClick($calculatedFees['paid_amount'])
            );

            $case->networkCasePayments()->save($networkCasePayment);

            $totalLicenseFee += $calculatedFees['license_fee'];
            $totalEventValue += $eventValue;
        }

        return new PaymentProcessingResult($totalEventValue, $totalLicenseFee);
    }

    public function addAdIncomeToUserLedger(AdsPayment $adsPayment): void
    {
        $splitPayments = NetworkCasePayment::fetchPaymentsForPublishersByAdsPaymentId($adsPayment->id);

        foreach ($splitPayments as $splitPayment) {
            if (null === ($user = User::fetchByUuid($splitPayment->publisher_id))) {
                Log::warning(
                    sprintf(
                        '[PaymentDetailsProcessor] User id (%s) does not exist. AdsPayment id (%s). Amount (%s).',
                        $splitPayment->publisher_id,
                        $adsPayment->id,
                        $splitPayment->paid_amount
                    )
                );

                continue;
            }

            UserLedgerEntry::constructWithAddressAndTransaction(
                $user->id,
                (int)$splitPayment->paid_amount,
                UserLedgerEntry::STATUS_ACCEPTED,
                UserLedgerEntry::TYPE_AD_INCOME,
                $adsPayment->address,
                $this->adServerAddress,
                $adsPayment->txid
            )->save();
        }
    }

    private function fetchLicenseFee(): float
    {
        try {
            $licenseFee = $this->licenseReader->getFee(Config::LICENCE_RX_FEE);
        } catch (ModelNotFoundException $modelNotFoundException) {
            throw new MissingInitialConfigurationException('No config entry for license fee.');
        }

        return $licenseFee;
    }

    private function fetchNetworkCasesForPaymentDetails(array $paymentDetails): Collection
    {
        $caseIds = [];
        foreach ($paymentDetails as $paymentDetail) {
            $caseIds[] = $paymentDetail['case_id'];
        }

        return NetworkCase::fetchByCaseIds($caseIds);
    }
}
