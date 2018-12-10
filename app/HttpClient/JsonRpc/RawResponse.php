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

use Adshares\Adserver\HttpClient\JsonRpc\Exception\RemoteCallException;
use Adshares\Adserver\HttpClient\JsonRpc\Exception\ResultException;
use Adshares\Adserver\HttpClient\JsonRpc\Result\ArrayResult;
use Adshares\Adserver\HttpClient\JsonRpc\Result\BoolResult;
use Adshares\Adserver\HttpClient\JsonRpc\Result\ObjectResult;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use function GuzzleHttp\json_decode;
use function is_array;
use function is_bool;

final class RawResponse
{
    private const FIELD_ID = 'id';

    private const FIELD_RESULT = 'result';

    private const FIELD_ERROR = 'error';

    /** @var ResponseInterface */
    private $response;

    /** @var [] */
    private $content;

    /** @var Procedure */
    private $procedure;

    /**
     * @throws RemoteCallException
     */
    public function __construct(ResponseInterface $response, Procedure $procedure)
    {
        $this->response = $response;
        $this->procedure = $procedure;
        $this->failIfInvalid();
    }

    /**
     * @throws RemoteCallException
     */
    private function failIfInvalid(): void
    {
        $statusCode = $this->response->getStatusCode();

        if ($statusCode !== Response::HTTP_OK) {
            throw  RemoteCallException::unexpectedStatusCode($statusCode);
        }

        try {
            $this->content = json_decode((string)$this->response->getBody(), true);
        } catch (InvalidArgumentException $e) {
            throw RemoteCallException::fromOther($e);
        }

        if (!isset($this->content[self::FIELD_ID]) || !$this->content[self::FIELD_ID]) {
            throw  RemoteCallException::missingField(self::FIELD_ID);
        }

        $id = $this->procedure->id();

        if ($this->content[self::FIELD_ID] !== $id) {
            throw RemoteCallException::mismatchedIds($id, $this->content[self::FIELD_ID]);
        }

        if (isset($this->content[self::FIELD_ERROR])) {
            throw RemoteCallException::fromResponseError($this->content[self::FIELD_ERROR]);
        }

        if (!isset($this->content[self::FIELD_RESULT])) {
            throw  RemoteCallException::missingField(self::FIELD_RESULT);
        }
    }

    public function result(): Result
    {
        $content = $this->content[self::FIELD_RESULT];

        if (is_bool($content)) {
            return new BoolResult($content);
        }

        if (is_array($content)) {
            if (empty($content) || $this->isSequential($content)) {
                return new ArrayResult($content);
            }

            return ObjectResult::fromArray($content);
        }

        throw new ResultException('Unsupported result type');
    }

    private function isSequential(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }

    private function hasStringKeys(array $array)
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }
}
