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

use Adshares\Common\Domain\ValueObject;
use Adshares\Common\Exception\InvalidArgumentException;

final class WalletAddress implements ValueObject
{
    private const SEPARATOR = ':';
    public const NETWORK_ADS = 'ads';
    public const NETWORK_BSC = 'bsc';

    private string $network;
    private string $address;

    public function __construct(string $network, string $address)
    {
        $this->network = self::normalizeNetwork($network);
        $this->address = self::normalizeAddress($this->network, $address);
        if (!self::isValid($this->toString())) {
            throw new InvalidArgumentException(sprintf('"%s" is NOT a VALID payout address.', $this));
        }
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    private static function normalizeNetwork($network): string
    {
        return strtolower($network);
    }

    private static function normalizeAddress($network, $address): string
    {
        switch ($network) {
            case self::NETWORK_ADS:
                return strtoupper($address);
            case self::NETWORK_BSC:
                return strtolower($address);
            default:
                return $address;
        }
    }

    private static function parse(string $value): ?array
    {
        $parts = explode(self::SEPARATOR, $value, 3);
        return count($parts) === 2 ? $parts : null;
    }

    public static function isValid(string $value): bool
    {
        if (null === ($parts = self::parse($value))) {
            return false;
        }
        $network = self::normalizeNetwork($parts[0]);
        $address = self::normalizeAddress($network, $parts[1]);
        switch ($network) {
            case self::NETWORK_ADS:
                return AccountId::isValid($address);
            case self::NETWORK_BSC:
                return !!preg_match('/^0x[0-9a-f]{40}$/i', $address);
            default:
                return false;
        }
    }

    public static function fromString(string $value): self
    {
        $parts = self::parse($value);
        return new self($parts[0] ?? '', $parts[1] ?? '');
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
