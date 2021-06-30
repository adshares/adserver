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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

final class AdsExchange
{
    /** @var string */
    private $apiUrl;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    public function __construct()
    {
        $this->apiUrl = config('app.exchange_api_url');
        $this->apiKey = config('app.exchange_api_key');
        $this->apiSecret = config('app.exchange_api_secret');
    }

    public function exchange(
        float $amount,
        string $currency,
        string $callbackUrl,
        ?string $paymentId = null,
        ?float $targetAmount = null
    ): bool {
        $data = [
            'operation' => 'exchange',
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'targetAmount' => $targetAmount,
            'targetCurrency' => 'ADS',
            'paymentId' => $paymentId,
            'callbackUrl' => $callbackUrl,
            'time' => time(),
        ];

        return $this->request($data);
    }

    public function transfer(
        float $amount,
        string $targetCurrency,
        string $targetAddress,
        string $callbackUrl,
        ?string $paymentId = null,
        ?float $targetAmount = null
    ): bool {
        $data = [
            'operation' => 'transfer',
            'amount' => $amount,
            'currency' => 'ADS',
            'targetAmount' => $targetAmount,
            'targetCurrency' => strtoupper($targetCurrency),
            'targetAddress' => $targetAddress,
            'paymentId' => $paymentId,
            'callbackUrl' => $callbackUrl,
            'time' => time(),
        ];

        return $this->request($data);
    }

    public function hash(array $params): string
    {
        $allowed = [
            'amount',
            'currency',
            'adsAmount',
            'paymentId',
            'callback',
            'time',
        ];

        $filtered = array_filter(
            $params,
            function ($key) use ($allowed) {
                return in_array($key, $allowed);
            },
            ARRAY_FILTER_USE_KEY
        );

        ksort($filtered);

        return hash_hmac(
            'sha512',
            json_encode($filtered, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES),
            $this->apiSecret
        );
    }

    private function request(array $data): bool
    {
        try {
            $client = new Client();
            $client->post(
                $this->apiUrl,
                [
                    RequestOptions::HEADERS => [
                        'x-api-key' => $this->apiKey,
                        'x-api-hash' => $this->hash($data),
                    ],
                    RequestOptions::JSON => $data,
                ]
            );
        } catch (RequestException $exception) {
            Log::error(sprintf('[Exchange] Cannot request exchange: %s', $exception->getMessage()));

            return false;
        }

        return true;
    }
}
