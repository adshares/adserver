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

namespace Adshares\Network;

use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\UrlInterface;

use function strtolower;
use function strtoupper;

final class BroadcastableUrl implements Broadcastable, UrlInterface
{
    /** @var Url */
    private $url;

    public function __construct(UrlInterface $url)
    {
        $this->url = $url;
    }

    public function toHex(): string
    {
        return strtoupper(unpack('H*', $this->url->toString())[1]);
    }

    public static function fromHex(string $hex): self
    {
        return new self(new Url(pack('H*', strtolower($hex))));
    }

    public function toString(): string
    {
        return $this->url->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
