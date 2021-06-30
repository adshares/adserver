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

namespace Adshares\Adserver\Client;

use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\SupplyClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

use function GuzzleHttp\json_decode;

final class GuzzleSupplyClient implements SupplyClient
{
    private const ENDPOINT_TARGETING_REACH = '/supply/targeting-reach';

    /** @var int */
    private $timeout;

    public function __construct(int $timeout)
    {
        $this->timeout = $timeout;
    }

    public function fetchTargetingReach(string $host): array
    {
        $client = new Client($this->requestParameters($host));

        try {
            $response = $client->get(self::ENDPOINT_TARGETING_REACH);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Could not connect to %s host (%s).', $host, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $this->validateResponse($response);

        return $this->createDecodedResponseFromBody((string)$response->getBody());
    }

    private function requestParameters(string $host): array
    {
        return [
            'base_uri' => $host,
            'headers' => [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
            ],
            'timeout' => $this->timeout,
        ];
    }

    private function validateResponse(ResponseInterface $response): void
    {
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new UnexpectedClientResponseException(
                sprintf('Unexpected response code `%d`.', $response->getStatusCode())
            );
        }
    }

    private function createDecodedResponseFromBody(string $body): array
    {
        try {
            $decoded = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Invalid json data.');
        }

        if (!isset($decoded['meta'])) {
            throw new UnexpectedClientResponseException('Missing `meta` field');
        }

        if (!isset($decoded['meta']['total_events_count'])) {
            throw new UnexpectedClientResponseException('Missing `meta.total_events_count` field');
        }

        if (!isset($decoded['categories'])) {
            throw new UnexpectedClientResponseException('Missing `categories` field');
        }

        return $decoded;
    }
}
