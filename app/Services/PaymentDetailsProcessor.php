<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Adserver\Models\NetworkBoostPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Dto\PaymentProcessingResult;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PaymentDetailsProcessor
{
    public function __construct(
        private readonly ExchangeRateReader $exchangeRateReader,
        private readonly LicenseReader $licenseReader,
    ) {
    }

    public function processPaidEvents(
        AdsPayment $adsPayment,
        array $paymentDetails,
        int $carriedEventValueSum
    ): PaymentProcessingResult {
        $exchangeRate = $this->fetchExchangeRate();
        $exchangeRateValue = $exchangeRate->getValue();

        $feeCalculator = new PaymentDetailsFeeCalculator($this->fetchLicenseFee(), config('app.payment_rx_fee'));
        $totalLicenseFee = 0;
        $totalOperatorFee = 0;
        $totalEventValue = 0;

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
                $adsPayment->tx_time,
                $adsPayment->id,
                $eventValue,
                $calculatedFees['license_fee'],
                $calculatedFees['operator_fee'],
                $calculatedFees['paid_amount'],
                $exchangeRateValue,
                $exchangeRate->fromClick($calculatedFees['paid_amount'])
            );

            $case->networkCasePayments()->save($networkCasePayment);

            $totalLicenseFee += $calculatedFees['license_fee'];
            $totalOperatorFee += $calculatedFees['operator_fee'];
            $totalEventValue += $eventValue;
        }

        return new PaymentProcessingResult(
            $totalEventValue,
            $totalLicenseFee,
            $totalOperatorFee,
            $totalEventValue - $totalLicenseFee - $totalOperatorFee,
        );
    }

    public function processBoost(
        AdsPayment $adsPayment,
        array $boostDetails,
        int $carriedEventValueSum,
    ): PaymentProcessingResult {
        $exchangeRate = $this->fetchExchangeRate();
        $exchangeRateValue = $exchangeRate->getValue();

        $feeCalculator = new PaymentDetailsFeeCalculator($this->fetchLicenseFee(), config('app.payment_rx_fee'));
        $totalLicenseFee = 0;
        $totalOperatorFee = 0;
        $totalEventValue = 0;

        $campaignIds = NetworkCampaign::findIdsByDemandIdsAndAddress(
            array_map(fn(array $detail) => $detail['campaign_id'], $boostDetails),
            $adsPayment->address,
        );

        foreach ($boostDetails as $boostDetail) {
            if (null === ($campaignId = $campaignIds[$boostDetail['campaign_id']] ?? null)) {
                continue;
            }

            $spendableAmount = max(0, $adsPayment->amount - $carriedEventValueSum - $totalEventValue);
            $value = min($spendableAmount, $boostDetail['value']);
            $calculatedFees = $feeCalculator->calculateFee($value);

            NetworkBoostPayment::create(
                $adsPayment->tx_time,
                $adsPayment->id,
                $campaignId,
                $value,
                $calculatedFees['license_fee'],
                $calculatedFees['operator_fee'],
                $calculatedFees['paid_amount'],
                $exchangeRateValue,
                $exchangeRate->fromClick($calculatedFees['paid_amount']),
            )->save();

            $totalLicenseFee += $calculatedFees['license_fee'];
            $totalOperatorFee += $calculatedFees['operator_fee'];
            $totalEventValue += $value;
        }

        return new PaymentProcessingResult(
            $totalEventValue,
            $totalLicenseFee,
            $totalOperatorFee,
            $totalEventValue - $totalLicenseFee - $totalOperatorFee,
        );
    }

    public function addAdIncomeToUserLedger(AdsPayment $adsPayment): void
    {
        $adServerAddress = config('app.adshares_address');
        $usePaidAmountCurrency = Currency::ADS !== Currency::from(config('app.currency'));
        $splitPayments = NetworkCasePayment::fetchPaymentsForPublishersByAdsPaymentId(
            $adsPayment->id,
            $usePaidAmountCurrency
        );

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

            $amount = (int)$splitPayment->paid_amount;
            UserLedgerEntry::constructWithAddressAndTransaction(
                $user->id,
                $amount,
                UserLedgerEntry::STATUS_ACCEPTED,
                UserLedgerEntry::TYPE_AD_INCOME,
                $adsPayment->address,
                $adServerAddress,
                $adsPayment->txid
            )->save();
        }
    }

    private function fetchExchangeRate(): ExchangeRate
    {
        $appCurrency = Currency::from(config('app.currency'));
        $currency = Currency::ADS === $appCurrency ? Currency::USD : $appCurrency;

        return $this->exchangeRateReader->fetchExchangeRate(null, $currency->value);
    }

    private function fetchLicenseFee(): float
    {
        return $this->licenseReader->getFee(LicenseReader::LICENSE_RX_FEE);
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
