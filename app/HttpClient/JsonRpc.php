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

final class JsonRpc
{
    /** @var ClientInterface */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param Procedure $procedure
     *
     * @return Result
     * @throws Exception\ErrorResponse
     * @throws Exception\ResponseException
     * @throws Exception\ResultException
     * @throws \Adshares\Common\Exception\Exception
     */
    public function call(Procedure $procedure): Result
    {
        try {
            $body = $procedure->toJson();

            $resp = $this->client->request(
                'POST',
                '/',
                [
                    'body' => $body,
                ]
            );
        } catch (GuzzleException $e) {
            throw Exception::fromOther($e);
        }

        return (new Response($resp, $procedure))->result();
    }
}
