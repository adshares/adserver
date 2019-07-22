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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Repository\Common\Dto\ClassifierExternal;
use Adshares\Common\Exception\RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

final class GuzzleClassifierExternalClient implements ClassifierExternalClient
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function requestClassification(ClassifierExternal $classifier, array $data): void
    {
        try {
            $this->client->post(
                $classifier->getUrl(),
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

    private function buildHeaders(ClassifierExternal $classifier): array
    {
        $userName = $classifier->getClientName();
        $userApiKey = $classifier->getClientApiKey();

        $nonce = Utils::urlSafeBase64Encode(substr(md5(uniqid()), 0, 16));
        $created = date('c');
        $digest = Utils::urlSafeBase64Encode(sha1(base64_decode($nonce).$created.$userApiKey, true));

        return [
            'Authorization' => 'WSSE profile="UsernameToken"',
            'X-WSSE' => sprintf(
                'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
                $userName,
                $digest,
                $nonce,
                $created
            ),
        ];
    }
}
