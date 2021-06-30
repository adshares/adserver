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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\GuzzleAdPayClient;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class GuzzleAdPayClientTest extends TestCase
{
    public function testUpdateBidStrategiesException(): void
    {
        $mock = new MockHandler([
            new Response(204),
            new RequestException('test-exception', new Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdPayClient = new GuzzleAdPayClient($client);

        $guzzleAdPayClient->updateBidStrategies([]);
        self::expectException(UnexpectedClientResponseException::class);
        $guzzleAdPayClient->updateBidStrategies([]);
    }

    public function testDeleteBidStrategiesException(): void
    {
        $mock = new MockHandler([
            new Response(204),
            new RequestException('test-exception', new Request('DELETE', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdPayClient = new GuzzleAdPayClient($client);

        $guzzleAdPayClient->deleteBidStrategies([]);
        self::expectException(UnexpectedClientResponseException::class);
        $guzzleAdPayClient->deleteBidStrategies([]);
    }

    public function testUpdateCampaignException(): void
    {
        $mock = new MockHandler([
            new Response(204),
            new RequestException('test-exception', new Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdPayClient = new GuzzleAdPayClient($client);

        $guzzleAdPayClient->updateCampaign([]);
        self::expectException(UnexpectedClientResponseException::class);
        $guzzleAdPayClient->updateCampaign([]);
    }

    public function testDeleteCampaignException(): void
    {
        $mock = new MockHandler([
            new Response(204),
            new RequestException('test-exception', new Request('DELETE', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $guzzleAdPayClient = new GuzzleAdPayClient($client);

        $guzzleAdPayClient->deleteCampaign([]);
        self::expectException(UnexpectedClientResponseException::class);
        $guzzleAdPayClient->deleteCampaign([]);
    }
}
