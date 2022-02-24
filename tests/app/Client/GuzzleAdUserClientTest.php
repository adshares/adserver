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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\GuzzleAdUserClient;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\TaxonomyV4;
use Adshares\Common\Exception\RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class GuzzleAdUserClientTest extends TestCase
{
    public function testFetchTargetingOptions(): void
    {
        $json = <<<JSON
{
  "meta": {
    "name": "simple",
    "version": "4.0.0"
  },
  "media": [
    {
      "name": "web",
      "label": "Website",
      "formats": [
        {
          "type": "direct",
          "mimes": [
            "text/plain"
          ],
          "scopes": {
            "pop-under": "Pop Under"
          }
        }
      ],
      "targeting": {
        "user": [
        ],
        "site": [
        ],
        "device": [
        ]
      }
    }
  ]
}
JSON;

        /** @var Client $client */
        $client = self::getMockBuilder(Client::class)
            ->setMethods(['get', 'getConfig'])
            ->getMock();
        $client->expects(self::once())->method('get')->willReturn(new Response(200, [], $json));
        $guzzleAdUserClient = new GuzzleAdUserClient($client);

        self::assertInstanceOf(TaxonomyV4::class, $guzzleAdUserClient->fetchTargetingOptions());
    }

    public function testFetchTargetingOptionsException(): void
    {
        $client = self::getMockBuilder(Client::class)
            ->setMethods(['get', 'getConfig'])
            ->getMock();
        $client->expects(self::once())->method('get')
            ->willThrowException(new RequestException('test exception', new Request('GET', '/api/v2/taxonomy')));
        $client->expects(self::once())->method('getConfig')->willReturn('https://example.com');
        /** @var Client $client */
        $guzzleAdUserClient = new GuzzleAdUserClient($client);

        self::expectException(RuntimeException::class);
        $guzzleAdUserClient->fetchTargetingOptions();
    }
}
