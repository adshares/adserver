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

namespace Adshares\Adserver\Services;

use Adshares\Adserver\Models\NowPaymentsLog;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

final class NowPayments
{
    const NOW_PAYMENTS_URL = 'https://nowpayments.io/payment';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $ipnSecret;

    /** @var string */
    private $currency;

    /** @var int */
    private $minAmount;

    /** @var float */
    private $fee;

    /** @var string */
    private $exchangeUrl;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    public function __construct(ExchangeRateReader $exchangeRateReader)
    {
        $this->exchangeRateReader = $exchangeRateReader;
        $this->apiKey = config('app.now_payments_api_key');
        $this->ipnSecret = config('app.now_payments_ipn_secret');
        $this->currency = config('app.now_payments_currency');
        $this->minAmount = (int)config('app.now_payments_min_amount');
        $this->fee = (float)config('app.now_payments_fee');
        $this->exchangeUrl = config('app.now_payments_exchange_url');
    }

    public function info(): ?array
    {
        return empty($this->apiKey)
            ? null
            : [
                'min_amount' => $this->minAmount,
                'exchange_rate' => $this->getExchangeRate(),
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
            'ipnURL' => route('now-payments.notify', ['uuid' => $user->uuid]),
            'successURL' => $panelUrl.'/now-payments/success',
            'cancelURL' => $panelUrl.'/now-payments/canceled',
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
            $log = NowPaymentsLog::create($user->id, $orderId, NowPaymentsLog::STATUS_INIT, $amount, null, $data);
            $log->save();
        } catch (QueryException $exception) {
            Log::error(sprintf('[NowPayments] Cannot save payment log: %s', $exception->getMessage()));
        }

        return sprintf('%s?data=%s', self::NOW_PAYMENTS_URL, rawurlencode(json_encode($data)));
    }

    public function notify(string $uuid, array $params): void
    {
        $user = User::fetchByUuid($uuid);

        $userId = $user !== null ? $user->id : 0;
        $orderId = $params['order_id'] ?? '';
        $status = $params['payment_status'] ?? '';
        $paymentId = $params['payment_id'] ?? null;
        $amount = $params['actually_paid'] ?? null;

        try {
            $log = NowPaymentsLog::create($userId, $orderId, $status, $amount, $paymentId, $params);
            $log->save();
        } catch (QueryException $exception) {
            Log::error(sprintf('[NowPayments] Cannot save payment log: %s', $exception->getMessage()));
        }
    }

    private function getExchangeRate(): float
    {
        try {
            $exchangeRate = $this->exchangeRateReader->fetchExchangeRate(null, $this->currency)->toArray();
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::error(sprintf('[NowPayments] Cannot fetch exchange rate: %s', $exception->getMessage()));

            return 0;
        }

        return $exchangeRate['value'] / (1 - $this->fee);
    }
}
