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

namespace Adshares\Common\Domain\ValueObject;

use Adshares\Common\Domain\Id;
use Adshares\Common\Domain\ValueObject;
use Adshares\Common\Exception\InvalidArgumentException;

use function dechex;
use function ord;
use function preg_match;
use function random_int;
use function sprintf;
use function str_pad;
use function strlen;

final class PayoutAddress implements ValueObject
{
    private const SEPARATOR = ':';

    private const NETWORKS = [
        'ads',
        'bsc'
    ];

    /** @var string */
    private $network;

    private $address;

    public function __construct(string $network, string $address)
    {
        $this->network = strtolower($network);
        $this->address = self::normalizeAddress($this->network, $address);
        if (!self::isValid($this->toString())) {
            throw new InvalidArgumentException("'{$this->toString()}' is NOT a"
                . ' VALID payout address.');
        }

    }

    private static function normalizeAddress($network, $address): string
    {
        switch($network) {
            case 'ads':
                return strtoupper($address);
            case 'bsc':
                return strtolower($address);
            default:
                return $address;
        }
    }

    public static function isValid(string $value): bool
    {
        $parts = explode(":", $value, 2);
        if(count($parts) != 2) {
            return false;
        }
        [$network, $address] = $parts;

        switch($network) {
            case 'ads':
                return AccountId::isValid($address, true);
            case 'bsc':
                return !!preg_match('/^0x[0-9a-f]{40}$/i', $address);
            default:
                return false;
        }
    }

    public static function fromString(string $value): self
    {
        [$network, $address] = explode(":", $value);

        return new self($network,  $address);
    }

    public function toString(): string
    {
        return $this->network . self::SEPARATOR . $this->address;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(object $other): bool
    {
        if (!($other instanceof self)) {
            return false;
        }

        return $this->toString() === $other->toString();
    }
}
