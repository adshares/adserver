<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Common\Exception;

use Exception as PhpException;
use Throwable;

use function json_decode;

class Exception extends PhpException
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function fromOther(PhpException $exception)
    {
        return new static($exception->getMessage(), $exception->getCode(), $exception);
    }

    public static function cleanMessage(string $message): string
    {
        $decoded = json_decode($message, true);

        if ($decoded && is_array($decoded)) {
            $message = $decoded['message'] ?? 'Unknown error';
        }

        return str_replace(["\n", "\t"], ' ', $message);
    }
}
