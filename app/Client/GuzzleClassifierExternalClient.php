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

use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;
use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Factory\TaxonomyFactory;
use Adshares\Common\Exception\RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;

use function GuzzleHttp\json_decode;

final class GuzzleClassifierExternalClient implements ClassifierExternalClient
{
    private const PATH_API = '/api/v0';

    private const PATH_REQUEST_CLASSIFICATION = '/requests';

    private const PATH_TAXONOMY = '/taxonomy';

    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function requestClassification(ClassifierExternal $classifier, array $data): void
    {
        $url = $classifier->getBaseUrl() . self::PATH_API . self::PATH_REQUEST_CLASSIFICATION;
        try {
            $this->client->post(
                $url,
                [
                    RequestOptions::JSON => $data,
                    RequestOptions::HEADERS => $this->buildHeaders($classifier),
                ]
            );
        } catch (RequestException $requestException) {
            throw new RuntimeException(
                $requestException->getMessage(),
                $requestException->getCode(),
                $requestException
            );
        }
    }

    public function fetchTaxonomy(ClassifierExternal $classifier): Taxonomy
    {
        $url = $classifier->getBaseUrl() . self::PATH_API . self::PATH_TAXONOMY;

        try {
            $result = $this->client->get(
                $url,
                [
                    RequestOptions::HEADERS => $this->buildHeaders($classifier),
                ]
            );
        } catch (RequestException $requestException) {
            throw new RuntimeException(
                $requestException->getMessage(),
                $requestException->getCode(),
                $requestException
            );
        }

        $body = (string)$result->getBody();
        try {
            $items = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return TaxonomyFactory::fromArray($items);
    }

    private function buildHeaders(ClassifierExternal $classifier): array
    {
        $apiKeyName = $classifier->getApiKeyName();
        $apiKeySecret = $classifier->getApiKeySecret();

        $nonce = base64_encode(substr(md5(uniqid()), 0, 16));
        $created = date('c');
        $digest = base64_encode(hash('sha256', base64_decode($nonce) . $created . $apiKeySecret, true));

        return [
            'Authorization' => 'WSSE profile="UsernameToken"',
            'X-WSSE' => sprintf(
                'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
                $apiKeyName,
                $digest,
                $nonce,
                $created
            ),
        ];
    }
}
