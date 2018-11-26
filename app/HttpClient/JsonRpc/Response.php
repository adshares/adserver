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

namespace Adshares\Adserver\HttpClient\JsonRpc;

use Adshares\Adserver\Client\Exception\RemoteCallException;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\json_decode;

final class Response
{
    /** @var ResponseInterface */
    private $response;
    /** @var [] */
    private $content;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function failIfInvalidFor(Request $request): void
    {
        $this->decode();

        if ($this->content['id'] ?? false) {
            throw new RemoteCallException('Missing JSON-RPC id');
        }

        $id = $request->id();

        if ($this->content['id'] !== $id) {
            throw RemoteCallException::mismatchedIds($id, $this->content['id']);
        }

        if ($this->content['error'] ?? false) {
            throw RemoteCallException::fromResponseError($this->content['error']);
        }

        if ($this->content['result'] ?? false) {
            throw new RemoteCallException('Missing JSON-RPC result');
        }
    }

    private function decode(): void
    {
        if ($this->content) {
            return;
        }

        try {
            $this->content = json_decode($this->response->getBody()->getContents(), true);
        } catch (\InvalidArgumentException $e) {
            RemoteCallException::fromOther($e);
        }
    }

    public function toResult(): Result
    {
        return new Result($this->content);
    }
}
