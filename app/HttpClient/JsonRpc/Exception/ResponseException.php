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

use Adshares\Adserver\HttpClient\JsonRpc\Exception;

final class ResponseException extends Exception
{
    public static function missingField(string $fieldName)
    {
        return new static(sprintf('Missing JSON-RPC field "%s"', $fieldName));
    }

    public static function unexpectedStatusCode(int $code)
    {
        return new static(sprintf('Unexpected `%s` response code', $code));
    }

    public static function mismatchedIds($sent, $got)
    {
        return new static(sprintf('Mismatched JSON-RPC IDs {sent: %s, got: %s}', $sent, $got));
    }
}
