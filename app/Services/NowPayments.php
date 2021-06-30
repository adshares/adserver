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

use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Mail\DepositProcessed;
use Adshares\Adserver\Models\NowPaymentsLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NowPayments
{
    private const NOW_PAYMENTS_API_URL = 'https://api.nowpayments.io/v1';

    private const NOW_PAYMENTS_URL = 'https://nowpayments.io/payment';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $ipnSecret;

    /** @var string */
    private $currency;

    /** @var int */
    private $minAmount;

    /** @var int */
    private $maxAmount;

    /** @var float */
    private $fee;

    /** @var string */
    private $exchangeUrl;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    /** @var AdsExchange */
    private $adsExchange;

    public function __construct(ExchangeRateReader $exchangeRateReader, AdsExchange $adsExchange)
    {
        $this->exchangeRateReader = $exchangeRateReader;
        $this->adsExchange = $adsExchange;
        $this->apiKey = config('app.now_payments_api_key');
        $this->ipnSecret = config('app.now_payments_ipn_secret');
        $this->currency = config('app.now_payments_currency');
        $this->minAmount = (int)config('app.now_payments_min_amount');
        $this->maxAmount = (int)config('app.now_payments_max_amount');
        $this->fee = (float)config('app.now_payments_fee');
        $this->useExchange = config('app.now_payments_exchange');
    }

    public function info(): ?array
    {
        return empty($this->apiKey)
            ? null
            : [
                'min_amount' => $this->minAmount,
                'max_amount' => $this->maxAmount,
                'exchange_rate' => $this->getExchangeRate() / (1 - $this->fee),
                'currency' => $this->currency,
            ];
    }

    public function getPaymentUrl(User $user, float $amount): string
    {
        $amount = round($amount, 2);
        $panelUrl = sprintf('%s/settings/billing', config('app.adpanel_url'));

        if ($amount < $this->minAmount) {
            Log::warning(
                sprintf('[NowPayments] Requested amount (%f) must be greater than %d', $amount, $this->minAmount)
            );

            return '';
        }

        $orderId = sprintf(
            '%s/%s',
            substr($user->uuid, 0, 10),
            substr(str_replace('.', '/', microtime(true)), 0, 13)
        );
        $data = [
            'dataSource' => "woocommerce",
            'apiKey' => $this->apiKey,
            'ipnURL' => SecureUrl::change(route('now-payments.notify', ['uuid' => $user->uuid])),
            'successURL' => $panelUrl . '/now-payments/success',
            'cancelURL' => $panelUrl . '/now-payments/canceled',
            'orderID' => $orderId,
            'customerEmail' => $user->email,
            'paymentCurrency' => $this->currency,
            'paymentAmount' => $amount,
            'products' => [
                [
                    'name' => sprintf('Deposit ADS into %s', config('app.name')),
                    'quantity' => 1,
                    'subtotal' => $amount,
                    'subtotal_tax' => 0,
                    'total' => $amount,
                    'total_tax' => 0,
                ],
            ],
        ];

        try {
            $log = NowPaymentsLog::create(
                $user->id,
                $orderId,
                NowPaymentsLog::STATUS_INIT,
                $amount,
                $this->currency,
                null,
                $data
            );
            $log->save();
        } catch (QueryException $exception) {
            Log::error(sprintf('[NowPayments] Cannot save payment log: %s', $exception->getMessage()));
        }

        return sprintf('%s?data=%s', self::NOW_PAYMENTS_URL, rawurlencode(json_encode($data)));
    }

    public function hash(array $params): string
    {
        ksort($params);

        return hash_hmac(
            'sha512',
            json_encode($params, JSON_UNESCAPED_SLASHES),
            $this->ipnSecret
        );
    }

    public function notify(User $user, array $params): bool
    {
        $orderId = (string)($params['order_id'] ?? '');
        $status = (string)($params['payment_status'] ?? '');
        $paymentId = (string)($params['payment_id'] ?? '');
        $amount = (float)($params['actually_paid'] ?? 0);
        $currency = strtoupper($params['pay_currency'] ?? '');

        try {
            $log = NowPaymentsLog::create(
                $user->id,
                $orderId,
                $status,
                $amount,
                $currency,
                $paymentId,
                $params
            );
            $log->save();
        } catch (QueryException $exception) {
            Log::error(sprintf('[NowPayments] Cannot save payment log: %s', $exception->getMessage()));
        }

        if ($status === NowPaymentsLog::STATUS_FINISHED) {
            return $this->prepareDeposit($user, $amount, $currency, $orderId, $paymentId);
        }

        return true;
    }

    public function exchange(User $user, array $params): bool
    {
        $orderId = $params['orderId'] ?? '';
        $paymentId = $params['paymentId'] ?? '';
        $amount = (float)($params['tragetAmount'] ?? 0);

        return $this->deposit($user, $amount, $orderId, $paymentId);
    }

    private function getExchangeRate(): float
    {
        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate(null, $this->currency)->toArray();
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::error(sprintf('[NowPayments] Cannot fetch exchange rate: %s', $exception->getMessage()));

            return 0;
        }

        return (float)$exchangeRate['value'];
    }

    private function saveDeposit(
        bool $processed,
        User $user,
        float $amount,
        string $orderId,
        string $paymentId,
        array $data = []
    ): bool {
        $ledgerTxId = sprintf('NP:%s', $paymentId);
        $entry = UserLedgerEntry::fetchByTxId($user->id, $ledgerTxId);

        if ($entry !== null && $entry->status === UserLedgerEntry::STATUS_ACCEPTED) {
            Log::error(
                sprintf('[NowPayments] Order %s (%s) is already deposited [#%d]', $orderId, $paymentId, $entry->id)
            );

            return false;
        }

        $clicks = AdsConverter::adsToClicks($amount);
        $status = $processed ? UserLedgerEntry::STATUS_ACCEPTED : UserLedgerEntry::STATUS_PROCESSING;
        if ($entry === null) {
            $entry = UserLedgerEntry::construct(
                $user->id,
                $clicks,
                $status,
                UserLedgerEntry::TYPE_DEPOSIT
            )->processed($ledgerTxId);
        } else {
            $entry->amount = $clicks;
            $entry->status = $status;
        }

        try {
            $entry->save();
        } catch (Throwable $throwable) {
            Log::error(
                sprintf('[NowPayments] Error during deposit (%s): %s', $paymentId, $throwable->getMessage())
            );

            return false;
        }

        try {
            $log = NowPaymentsLog::create(
                $user->id,
                $orderId,
                $processed ? NowPaymentsLog::STATUS_DEPOSIT : NowPaymentsLog::STATUS_DEPOSIT_INIT,
                $amount,
                'ADS',
                $paymentId,
                array_merge(
                    $data,
                    [
                        'ledgerTxId' => $ledgerTxId,
                        'clicks' => $clicks,
                    ]
                )
            );
            $log->save();
        } catch (QueryException $exception) {
            Log::error(sprintf('[NowPayments] Cannot save payment log: %s', $exception->getMessage()));
        }

        return true;
    }

    private function prepareDeposit(
        User $user,
        float $amount,
        string $currency,
        string $orderId,
        string $paymentId
    ): bool {
        $middleAmount = $this->getEstimatePrice($amount, $currency);
        $middleFee = $middleAmount * $this->fee;
        $exchangeAmount = $middleAmount - $middleFee;
        $adsAmount = $exchangeAmount / $this->getExchangeRate();

        $result = $this->saveDeposit(
            false,
            $user,
            $adsAmount,
            $orderId,
            $paymentId,
            [
                'amount' => $amount,
                'currency' => $currency,
                'middleAmount' => $middleAmount,
                'middleFee' => $middleFee,
                'middleCurrency' => $this->currency,
                'adsAmount' => $adsAmount,
            ]
        );

        if (!$result) {
            return false;
        }

        if ($this->useExchange) {
            return $this->exchangeDeposit($user, $exchangeAmount, $adsAmount, $paymentId);
        } else {
            return $this->deposit($user, $adsAmount, $orderId, $paymentId);
        }
    }

    private function deposit(User $user, float $amount, string $orderId, string $paymentId): bool
    {
        if ($amount == 0) {
            Log::warning('[NowPayments] Cannot deposit 0 ADS');

            return false;
        }

        if ($this->saveDeposit(true, $user, $amount, $orderId, $paymentId)) {
            Mail::to($user)->queue(new DepositProcessed(AdsConverter::adsToClicks($amount)));

            return true;
        }

        return false;
    }

    private function exchangeDeposit(
        User $user,
        float $amount,
        float $adsAmount,
        string $paymentId
    ): bool {
        if ($amount == 0) {
            Log::warning('[NowPayments] Cannot exchange 0 ADS');

            return false;
        }

        return $this->adsExchange->exchange(
            $amount,
            $this->currency,
            SecureUrl::change(route('now-payments.exchange', ['uuid' => $user->uuid])),
            $paymentId,
            $adsAmount
        );
    }

    public function getEstimatePrice(float $amount, string $currency): float
    {
        $rate = 0;
        try {
            $client = new Client();
            $response = $client->get(
                self::NOW_PAYMENTS_API_URL . '/estimate?' . http_build_query(
                    ['amount' => 100, 'currency_from' => $this->currency, 'currency_to' => $currency]
                ),
                [RequestOptions::HEADERS => ['x-api-key' => $this->apiKey]]
            );
            if ($response->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode((string)$response->getBody(), true);
                $rate = 100 / (float)($data['estimated_amount'] ?? 0);
            }
        } catch (RequestException $exception) {
            Log::error(
                sprintf('[NowPayments] Cannot get estimated price for "%s": %s', $currency, $exception->getMessage())
            );

            return 0;
        }

        return $amount * $rate;
    }
}
