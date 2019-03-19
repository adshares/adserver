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

use Adshares\Common\Application\Service\LicenseProvider;
use Adshares\Common\Domain\ValueObject\License;
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

    public function __construct(Client $client, string $licenseId)
    {
        $this->client = $client;
        $this->licenseId = $licenseId;
    }

    public function get()
    {
        $uri = self::GET_ENDPOINT.$this->licenseId;

        try {
            $response = $this->client->get($uri);
        } catch (RequestException $exception) {
            $message = 'Could not connect to LICENSE SERVER (%s) (Exception: %s).';
            throw new UnexpectedClientResponseException(
                sprintf($message, $this->client->getConfig('base_uri'), $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }


        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);

        $this->decodeResponse($decoded['data']);

        return new License();
    }

    private function decodeResponse(string $data)
    {
        $licenseKey = 'SRV-sEr4tG-Ol3Em-Dkem9-8Juy-5298';

        $raw = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        $iv = substr($raw, 0, $ivlen);
        $hmac = substr($raw, $ivlen, $sha2len = 32);
        $encrypted = substr($raw, $ivlen + $sha2len);
        $data = openssl_decrypt(
            $encrypted,
            $cipher,
            $licenseKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        $calcmac = hash_hmac('sha256', $encrypted, $licenseKey, true);

        if (hash_equals($hmac, $calcmac)) {

            dump(json_decode($data));
        }

    }

}
