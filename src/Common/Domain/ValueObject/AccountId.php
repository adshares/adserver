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

namespace Adshares\Common\Domain\ValueObject;

use Adshares\Common\Id;
use InvalidArgumentException;
use function preg_match;
use function sprintf;

final class AccountId implements Id
{
    private const LOOSELY_VALID_CHECKSUM = 'XXXX';

    /** @var string */
    private $accountIdStringRepresentation;

    private function __construct(string $string)
    {
        $this->accountIdStringRepresentation = $string;
    }

    public static function random(bool $strict = true): AccountId
    {
        $nodeId = str_pad(dechex(random_int(0, 2047)), 4, '0', STR_PAD_LEFT);
        $userId = str_pad(dechex(random_int(0, 2047)), 8, '0', STR_PAD_LEFT);

        return self::fromIncompleteString("{$nodeId}-{$userId}", $strict);
    }

    public static function fromIncompleteString(string $string, bool $strict = true): AccountId
    {
        if (1 === preg_match('/^[0-9A-F]{4}-[0-9A-F]{8}$/i', $string)) {
            $checksum = $strict
                ? self::checksum($string)
                : self::LOOSELY_VALID_CHECKSUM;

            return new self(strtoupper("{$string}-{$checksum}"));
        }

        throw new InvalidArgumentException("'$string' is not a valid 'NODE-USER' string.");
    }

    private static function checksum(string $string): string
    {
        $nodeId = substr($string, 0, 4);
        $userId = substr($string, 5, 8);

        return sprintf('%04X', self::crc16(sprintf('%04X%08X', hexdec($nodeId), hexdec($userId))));
    }

    private static function crc16(string $hexChars): int
    {
        $chars = hex2bin($hexChars);
        if ($chars) {
            $crc = 0x1D0F;
            for ($i = 0, $iMax = \strlen($chars); $i < $iMax; $i++) {
                $x = ($crc >> 8) ^ \ord($chars[$i]);
                $x ^= $x >> 4;
                $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
            }
        } else {
            $crc = 0;
        }

        return $crc;
    }

    public static function fromString(string $string, bool $strict = false): AccountId
    {
        if (!self::isValid($string, $strict)) {
            throw new InvalidArgumentException("'$string' is NOT a"
                .($strict ? ' STRICTLY' : '')
                .' VALID AccountId representation.');
        }

        return new self(strtoupper($string));
    }

    public static function isValid(string $string, bool $strict = false): bool
    {
        $pattern = '/^[0-9A-F]{4}-[0-9A-F]{8}-([0-9A-F]{4}|'
            .self::LOOSELY_VALID_CHECKSUM
            .')$/i';

        if (1 === preg_match($pattern, $string)) {
            $checksum = strtoupper(substr($string, -4));

            if (self::LOOSELY_VALID_CHECKSUM === $checksum) {
                return !$strict;
            }

            return $checksum === self::checksum($string);
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->accountIdStringRepresentation;
    }

    public function equals(Id $other, bool $strict = false): bool
    {
        if ($strict) {
            return $this->accountIdStringRepresentation === $other->toString();
        }

        return strpos($other->toString(), substr($this->accountIdStringRepresentation, 0, 13)) === 0;
    }
}
