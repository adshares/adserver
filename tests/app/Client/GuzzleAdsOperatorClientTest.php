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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\GuzzleAdsOperatorClient;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class GuzzleAdsOperatorClientTest extends TestCase
{
    public function testFetchExchangeRate(): void
    {
        $mock = new MockHandler([
            new Response(body: '{"rate": 0.3333}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdsOperatorClient = new GuzzleAdsOperatorClient($client);

        $exchangeRate = $guzzleAdsOperatorClient->fetchExchangeRate();

        self::assertEquals(0.3333, $exchangeRate->getValue());
    }

    public function testFetchExchangeRateFailOnClientException(): void
    {
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdsOperatorClient = new GuzzleAdsOperatorClient($client);

        self::expectException(ExchangeRateNotAvailableException::class);

        $guzzleAdsOperatorClient->fetchExchangeRate();
    }

    public function testFetchExchangeRateFailOnResponseInvalid(): void
    {
        $mock = new MockHandler([
            new Response(body: '0.3333'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdsOperatorClient = new GuzzleAdsOperatorClient($client);

        self::expectException(ExchangeRateNotAvailableException::class);

        $guzzleAdsOperatorClient->fetchExchangeRate();
    }
}
