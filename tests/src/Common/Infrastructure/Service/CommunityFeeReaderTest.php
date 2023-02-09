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

namespace Adshares\Tests\Common\Infrastructure\Service;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Infrastructure\Service\CommunityFeeReader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class CommunityFeeReaderTest extends TestCase
{
    public function testGetAddress(): void
    {
        $reader = $this->getCommunityFeeReader();

        self::assertEquals('0001-00000024-FF89', $reader->getAddress()->toString());
    }

    public function testGetFee(): void
    {
        $reader = $this->getCommunityFeeReader();

        self::assertEquals(0.01, $reader->getFee());
    }

    /**
     * @dataProvider getFeeInvalidProvider
     */
    public function testGetFeeInvalid(string $body): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([new Response(body: $body)]));
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $reader = new CommunityFeeReader($client);

        self::assertEquals(0.01, $reader->getFee());
    }

    public function getFeeInvalidProvider(): array
    {
        return [
            'no community field' => ['{}'],
            'invalid community type' => ['{"community": 0.01}'],
        ];
    }

    public function testGetFeeOnClientError(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([new Response(404)]));
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);
        $reader = new CommunityFeeReader($client);

        self::assertEquals(0.01, $reader->getFee());
    }

    private function getCommunityFeeReader(): CommunityFeeReader
    {
        $handlerStack = HandlerStack::create(
            new MockHandler([
                new Response(body: file_get_contents(base_path('tests/mock/network.json'))),
            ])
        );
        $client = new Client(['base_uri' => 'https://example.com', 'handler' => $handlerStack]);

        return new CommunityFeeReader($client);
    }
}
