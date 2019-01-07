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

use Adshares\Adserver\HttpClient\JsonRpc\Exception\ErrorResponseException;
use Adshares\Adserver\HttpClient\JsonRpc\Exception\ResponseException;
use Adshares\Adserver\HttpClient\JsonRpc\Exception\ResultException;
use Adshares\Adserver\HttpClient\JsonRpc\Result\ArrayResult;
use Adshares\Adserver\HttpClient\JsonRpc\Result\BoolResult;
use Adshares\Adserver\HttpClient\JsonRpc\Result\ObjectResult;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use function GuzzleHttp\json_decode;
use function is_array;
use function is_bool;

final class Response
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
     * @throws ErrorResponseException
     * @throws ResponseException
     * @throws \Adshares\Common\Exception\Exception
     */
    public function __construct(ResponseInterface $response, Procedure $procedure)
    {
        $this->response = $response;
        $this->procedure = $procedure;
        $this->failIfInvalidResponse();
        $this->failOnResponseError();
        $this->failIfNoResult();
    }

    /**
     * @throws ResponseException
     * @throws \Adshares\Common\Exception\Exception
     */
    private function failIfInvalidResponse(): void
    {
        $statusCode = $this->response->getStatusCode();

        if ($statusCode !== HttpResponse::HTTP_OK) {
            throw  ResponseException::unexpectedStatusCode($statusCode);
        }

        try {
            $this->content = json_decode((string)$this->response->getBody(), true);
        } catch (InvalidArgumentException $e) {
            throw ResponseException::fromOther($e);
        }

        $responseId = $this->content[self::FIELD_ID] ?? false;
        if (!$responseId) {
            throw  ResponseException::missingField(self::FIELD_ID);
        }

        $id = $this->procedure->id();

        if ($responseId !== $id) {
            throw ResponseException::mismatchedIds($id, $responseId);
        }
    }

    private function failOnResponseError(): void
    {
        $responseError = $this->content[self::FIELD_ERROR] ?? [];
        if ((bool)$responseError) {
            throw ErrorResponseException::fromResponseError($responseError);
        }
    }

    private function failIfNoResult(): void
    {
        if (!isset($this->content[self::FIELD_RESULT])) {
            throw  ResponseException::missingField(self::FIELD_RESULT);
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
}
