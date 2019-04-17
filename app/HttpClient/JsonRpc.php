<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\HttpClient;

use Adshares\Adserver\HttpClient\JsonRpc\Exception;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Adserver\HttpClient\JsonRpc\Response;
use Adshares\Adserver\HttpClient\JsonRpc\Result;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Throwable;
use function json_decode;

final class JsonRpc
{
    /** @var ClientInterface */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function call(Procedure $procedure): Result
    {
        $body = $procedure->toJson();

        try {
            $response = $this->client->request('POST', '/', ['body' => $body]);

            Log::debug(sprintf(
                '{"url": "%s", "body": %s, "result": %s}',
                (string)$this->client->getConfig('base_uri'),
                $body,
                str_replace(["\n", "\r"], ' ', (string)$response->getBody())
            ));

            return (new Response($response, $procedure))->result();
        } catch (GuzzleException $e) {
            $decoded = json_decode($e->getMessage(), true) ?? [];

            throw Exception::onError(
                $procedure,
                (string)$this->client->getConfig('base_uri'),
                $body,
                'GuzzleException: '.($decoded['message'] ?? 'Unknown error')
            );
        } catch (Throwable $e) {
            throw Exception::onError(
                $procedure,
                (string)$this->client->getConfig('base_uri'),
                $body,
                str_replace(["\n", "\r"], ' ', $e->getMessage())
            );
        }
    }
}
