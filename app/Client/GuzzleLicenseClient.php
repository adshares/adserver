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

use Adshares\Common\Application\Dto\EncodedLicense;
use Adshares\Common\Application\Service\LicenseProvider;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use function json_decode;

class GuzzleLicenseClient implements LicenseProvider
{
    private const GET_ENDPOINT = '/api/v1/license/';

    /** @var Client */
    private $client;
    /** @var string */
    private $licenseId;

    public function __construct(
        Client $client,
        string $licenseId
    ) {
        $this->client = $client;
        $this->licenseId = $licenseId;
    }

    public function fetchLicense(): EncodedLicense
    {
        $uri = self::GET_ENDPOINT . $this->licenseId;

        try {
            $response = $this->client->get($uri);
        } catch (RequestException $exception) {
            $message = 'Could not download a license (%s) from LICENSE SERVER (%s/%s).';
            throw new UnexpectedClientResponseException(
                sprintf($message, $this->licenseId, $this->client->getConfig('base_uri'), $uri),
                $exception->getCode(),
                $exception
            );
        }

        $body = json_decode((string)$response->getBody());

        if (!isset($body->data)) {
            throw new UnexpectedClientResponseException('Unexpected data format from a License Server.');
        }

        return new EncodedLicense($body->data);
    }
}
