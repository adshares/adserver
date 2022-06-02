<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Client;

use Adshares\Common\Application\Dto\Gateway;
use Adshares\Common\Application\Service\AdsRpcClient;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class GuzzleAdsRpcClient implements AdsRpcClient
{
    private const TTL_ONE_HOUR = 3600;
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getGateway(string $code): Gateway
    {
        foreach ($this->getGateways() as $gateway) {
            if (strtoupper($code) === $gateway->getCode()) {
                return $gateway;
            }
        }
        throw new RuntimeException(sprintf('Cannot find gateway "%s"', $code));
    }

    public function getGatewayFee(string $code, int $amount, string $address): int
    {
        return $this->request('get_gateway_fee', [
            'code' => $code,
            'amount' => $amount,
            'address' => $address
        ])['fee'];
    }

    /**
     * @return Gateway[]
     */
    public function getGateways(): array
    {
        return array_map(
            fn($data) => Gateway::fromArray($data),
            Cache::remember(
                'ads-rpc-client.gateways',
                self::TTL_ONE_HOUR,
                fn() => $this->request('get_gateways')['gateways']
            )
        );
    }

    private function request(string $method, array $params = []): array
    {
        $response = $this->client->post(
            '/',
            [
                RequestOptions::JSON => [
                    'id' => rand(1, 999999),
                    'method' => $method,
                    'params' => $params,
                ],
            ]
        );

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('RPC Server Communication Error: %d', $response->getStatusCode()));
        }

        $content = $response->getBody()->getContents();
        if (null === ($data = json_decode($content, true))) {
            throw new RuntimeException(sprintf('RPC Server Malformed Data Error: %s', $content));
        }

        if (array_key_exists('error', $data)) {
            $message = $data['error']['message'];
            if (array_key_exists('data', $data['error'])) {
                $message .= ' - ' . $data['error']['data'];
            }
            throw new RuntimeException($message);
        }

        return $data['result'];
    }
}
