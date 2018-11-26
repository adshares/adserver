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

namespace Adshares\Adserver\Client\Exception;

use Exception;
use Throwable;

final class RemoteCallException extends Exception
{
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
        return new self($error['message'], (int)$error['code']);
    }

    public static function mismatchedIds($sent, $got): self
    {
        return new self(sprintf('Mismatched JSON-RPC IDs {sent: %s, got: %s}', $sent, $got));
    }
}
