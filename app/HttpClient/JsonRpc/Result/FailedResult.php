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

namespace Adshares\Adserver\HttpClient\JsonRpc\Result;

use Adshares\Adserver\HttpClient\JsonRpc\Result;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Exception\RuntimeException;
use Throwable;
use function get_class;
use function sprintf;

final class FailedResult implements Result
{
    /** @var string */
    private $message;

    public function __construct(string $message, Throwable $e = null)
    {
        $this->message = sprintf(
            '%s %s %s',
            $message,
            $e ? get_class($e) : '',
            $e ? Exception::cleanMessage($e->getMessage()) : ''
        );
    }

    public function toArray(): array
    {
        throw new RuntimeException("FAILED toArray {$this->message}");
    }

    public function isTrue(): bool
    {
        throw new RuntimeException("FAILED isTrue {$this->message}");
    }

    public function failed(): bool
    {
        return true;
    }

    public function getCount(): int
    {
        throw new RuntimeException(sprintf('%s %s', __CLASS__, $this->message));
    }
}
