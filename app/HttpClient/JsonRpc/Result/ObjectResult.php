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

use Adshares\Adserver\HttpClient\JsonRpc\Exception\ResultException;
use Adshares\Adserver\HttpClient\JsonRpc\Result;

final class ObjectResult implements Result
{
    /** @var object */
    private $content;

    public function __construct(object $content)
    {
        $this->content = $content;
    }

    public static function fromArray(array $content): self
    {
        return new self((object)$content);
    }

    public function toArray(): array
    {
        return (array)$this->content;
    }

    public function isTrue(): bool
    {
        throw new ResultException('This is an `array`');
    }

    public function failed(): bool
    {
        return false;
    }
}
