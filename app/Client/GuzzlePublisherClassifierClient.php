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

namespace Adshares\Adserver\Client;

use Adshares\Supply\Application\Dto\ClassifiedBanners;
use Adshares\Supply\Application\Service\ClassifierClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Response;
use function json_decode;

class GuzzlePublisherClassifierClient implements ClassifierClient
{
    private const VERIFY_ENDPOINT = '/api/verify';

    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function verify(array $bannerIds): ClassifiedBanners
    {
        $body = json_encode($bannerIds);

        try {
            $response = $this->client->post(self::VERIFY_ENDPOINT, ['body' => $body]);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Could not connect to %s host (%s).', $this->client->getConfig('base_uri'), $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);
        $decodedResponse = json_decode($body, true);

        return new ClassifiedBanners($decodedResponse);
    }

    private function validateResponse(int $statusCode, string $body): void
    {
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`.', $statusCode));
        }
    }
}
