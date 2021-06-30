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

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\InvalidUrlException;
use DateTime;

class SourceCampaign
{
    /** @var string */
    private $host;

    /** @var string */
    private $address;

    /** @var string */
    private $version;

    /** @var DateTime */
    private $createdAt;

    /** @var DateTime|null */
    private $updatedAt;

    public function __construct(
        string $host,
        string $address,
        string $version,
        DateTime $createdAt,
        ?DateTime $updatedAt
    ) {
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            throw new InvalidUrlException(sprintf('Host value `%s` is invalid. It must be a valid URL.', $host));
        }

        $this->host = $host;
        $this->address = $address;
        $this->version = $version;
        $this->updatedAt = $updatedAt;
        $this->createdAt = $createdAt;
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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }
}
