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
    /** @var Procedure */
    private $procedure;

    public function __construct(ResponseInterface $response, Procedure $procedure)
    {
        $this->response = $response;
        $this->procedure = $procedure;
    }

    public function failIfInvalid(): void
    {
        $this->decode();

        if (!isset($this->content['id']) || !$this->content['id']) {
            throw  RemoteCallException::missingField('id');
        }

        $id = $this->procedure->id();

        if ($this->content['id'] !== $id) {
            throw RemoteCallException::mismatchedIds($id, $this->content['id']);
        }

        if (isset($this->content['error'])) {
            throw RemoteCallException::fromResponseError($this->content['error']);
        }

        if (!isset($this->content['result'])) {
            throw  RemoteCallException::missingField('result');
        }
    }

    private function decode(): void
    {
        if (!empty($this->content)) {
            return;
        }

        try {
            $this->content = json_decode($this->response->getBody()->getContents(), true);
        } catch (\InvalidArgumentException $e) {
            RemoteCallException::fromOther($e);
        }

        if (empty($this->content)) {
            throw new RemoteCallException('Missing JSON-RPC');
        }
    }

    public function toResult(): Result
    {
        return new Result($this->content);
    }
}
