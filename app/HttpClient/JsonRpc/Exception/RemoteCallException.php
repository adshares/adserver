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

namespace Adshares\Adserver\HttpClient\JsonRpc\Exception;

use Exception;
use Throwable;

final class RemoteCallException extends Exception
{
    private const FIELD_ERROR_MESSAGE = 'message';
    private const FIELD_ERROR_CODE = 'code';

    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function fromOther(Exception $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode());
    }

    public static function fromResponseError(array $error): self
    {
        return new self($error[self::FIELD_ERROR_MESSAGE], (int)$error[self::FIELD_ERROR_CODE]);
    }

    public static function missingField(string $fieldName): self
    {
        return new self(sprintf('Missing JSON-RPC field "%s"', $fieldName));
    }

    public static function unexpectedStatusCode(int $code): self
    {
        return new self(sprintf('Unexpected `%s` response code', $code));
    }

    public static function mismatchedIds($sent, $got): self
    {
        return new self(sprintf('Mismatched JSON-RPC IDs {sent: %s, got: %s}', $sent, $got));
    }
}
