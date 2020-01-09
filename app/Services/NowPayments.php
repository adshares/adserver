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

use Adshares\Adserver\Models\User;

final class NowPayments
{
    const NOW_PAYMENTS_URL = 'https://nowpayments.io/payment';

    private $apiKey;

    private $ipnSecret;

    private $currency;

    private $minAmount;

    private $fee;

    private $exchangeUrl;

    public function __construct()
    {
        $this->apiKey = config('app.now_payments_api_key');
        $this->ipnSecret = config('app.now_payments_ipn_secret');
        $this->currency = config('app.now_payments_currency');
        $this->minAmount = (int)config('app.now_payments_min_amount');
        $this->fee = (float)config('app.now_payments_fee');
        $this->exchangeUrl = config('app.now_payments_exchange_url');
    }

    public function getPaymentUrl(User $user, float $amount): string
    {
        $amount = round($amount, 2);
        $panelUrl = sprintf('%s/settings/billing', config('app.adpanel_url'));

        if ($amount < $this->minAmount) {
            return $panelUrl;
        }

        $data = [
            'dataSource' => "woocommerce",
            'apiKey' => $this->apiKey,
            'ipnURL' => route('now-payments.notify', ['uuid' => $user->uuid]),
            'successURL' => $panelUrl.'/now-payments/success',
            'cancelURL' => $panelUrl.'/now-payments/canceled',
            'orderID' => sprintf('%s/%s', substr($user->uuid, 0, 10), str_replace('.', '', microtime(true))),
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

        return sprintf('%s?data=%s', self::NOW_PAYMENTS_URL, rawurlencode(json_encode($data)));
    }
}
