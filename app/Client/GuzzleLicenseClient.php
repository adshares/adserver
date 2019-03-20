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

use Adshares\Common\Application\Service\LicenseDecoder;
use Adshares\Common\Application\Service\LicenseProvider;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use function json_decode;

class GuzzleLicenseClient implements LicenseProvider
{
    private const GET_ENDPOINT = '/api/v1/license/';

    /** @var Client */
    private $client;
    /** @var LicenseVault */
    private $licenseVault;
    /** @var LicenseDecoder */
    private $licenseDecoder;
    /** @var string */
    private $licenseId;

    public function __construct(
        Client $client,
        string $licenseId,
        LicenseDecoder $licenseDecoder,
        LicenseVault $licenseVault
    ) {
        $this->client = $client;
        $this->licenseVault = $licenseVault;
        $this->licenseDecoder = $licenseDecoder;
        $this->licenseId = $licenseId;
    }

    public function get()
    {
        $uri = self::GET_ENDPOINT.$this->licenseId;

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

        $this->licenseDecoder->decode($body->data);
        $this->licenseVault->store($body->data);
    }
}
