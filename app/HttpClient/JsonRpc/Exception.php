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

use Adshares\Common\Exception\Exception as AdsharesException;
use Illuminate\Support\Facades\Log;
use Throwable;
use function get_class;
use function sprintf;

class Exception extends AdsharesException
{
    public static function onError(Procedure $procedure, string $base_url, string $body, Throwable $e): void
    {
        Log::error(sprintf(
            '%s: %s {"url": "%s", "method": "%s"}',
            get_class($e),
            self::cleanMessage($e->getMessage()),
            $base_url,
            $procedure->method()
        ));
//        throw new static(sprintf(
//            '%s: %s {"url": "%s", "method": "%s", "body": %s}',
//            get_class($e),
//            self::cleanMessage($e),
//            $base_url,
//            $procedure->method(),
//            $body
//        ));
    }
}
