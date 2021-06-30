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
use Adshares\Common\Exception\InvalidArgumentException;

use function dechex;
use function ord;
use function preg_match;
use function random_int;
use function sprintf;
use function str_pad;
use function strlen;

final class AccountId implements Id
{
    private const LOOSELY_VALID_CHECKSUM = 'XXXX';

    /** @var string */
    private $value;

    public function __construct(string $value, bool $strict = false)
    {
        if (!self::isValid($value, $strict)) {
            throw new InvalidArgumentException("'$value' is NOT a"
                . ($strict ? ' STRICTLY' : '')
                . ' VALID AccountId representation.');
        }

        $this->value = strtoupper($value);
    }

    public static function isValid(string $value, bool $strict = false): bool
    {
        $pattern = '/^[0-9A-F]{4}-[0-9A-F]{8}-([0-9A-F]{4}|'
            . self::LOOSELY_VALID_CHECKSUM
            . ')$/i';

        if (1 === preg_match($pattern, $value)) {
            $checksum = strtoupper(substr($value, -4));

            if (self::LOOSELY_VALID_CHECKSUM === $checksum) {
                return !$strict;
            }

            return $checksum === self::checksum($value);
        }

        return false;
    }

    private static function checksum(string $value): string
    {
        $nodeId = substr($value, 0, 4);
        $userId = substr($value, 5, 8);

        return sprintf('%04X', self::crc16(sprintf('%04X%08X', hexdec($nodeId), hexdec($userId))));
    }

    private static function crc16(string $hexChars): int
    {
        $chars = hex2bin($hexChars);
        if ($chars) {
            $crc = 0x1D0F;
            for ($i = 0, $iMax = strlen($chars); $i < $iMax; $i++) {
                $x = ($crc >> 8) ^ ord($chars[$i]);
                $x ^= $x >> 4;
                $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
            }
        } else {
            $crc = 0;
        }

        return $crc;
    }

    public static function random(bool $strict = true): AccountId
    {
        $nodeId = str_pad(dechex(random_int(0, 2047)), 4, '0', STR_PAD_LEFT);
        $userId = str_pad(dechex(random_int(0, 2047)), 8, '0', STR_PAD_LEFT);

        return self::fromIncompleteString("{$nodeId}-{$userId}", $strict);
    }

    public static function fromIncompleteString(string $value, bool $strict = true): AccountId
    {
        if (1 === preg_match('/^[0-9A-F]{4}-[0-9A-F]{8}$/i', $value)) {
            $checksum = $strict
                ? self::checksum($value)
                : self::LOOSELY_VALID_CHECKSUM;

            return new self("{$value}-{$checksum}");
        }

        throw new InvalidArgumentException("'$value' is not a valid 'NODE-ACCOUNT' string.");
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(object $other, bool $strict = false): bool
    {
        if (!($other instanceof self)) {
            return false;
        }

        if ($strict) {
            return $this->value === $other->value;
        }

        return strpos($other->value, substr($this->value, 0, 13)) === 0;
    }
}
