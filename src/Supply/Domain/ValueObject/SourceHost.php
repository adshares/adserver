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

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\InvalidUrlException;

class SourceHost
{
    /** @var string */
    private $host;

    /** @var string */
    private $address;

    /** @var string */
    private $version;

    public function __construct(
        string $host,
        string $address,
        string $version
    ) {
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            throw new InvalidUrlException(sprintf('Host value `%s` is invalid. It must be a valid URL.', $host));
        }

        $this->host = $host;
        $this->address = $address;
        $this->version = $version;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
