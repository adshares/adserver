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

use Adshares\Ads\Util\AdsChecksumGenerator;
use Adshares\Common\Id;
use InvalidArgumentException;

final class AccountId implements Id
{
    private const LOOSELY_VALID_CHECKSUM = 'XXXX';

    /** @var string */
    private $account;

    private function __construct(string $account)
    {
        $this->account = $account;
    }

    public static function random(bool $strict = true): AccountId
    {
        $nodeId = str_pad(dechex(random_int(0, 2047)), 4, '0', STR_PAD_LEFT);
        $userId = str_pad(dechex(random_int(0, 2047)), 8, '0', STR_PAD_LEFT);
        $checksum = $strict
            ? AdsChecksumGenerator::getAccountChecksum(hexdec($nodeId), hexdec($userId))
            : self::LOOSELY_VALID_CHECKSUM;

        return self::fromString("{$nodeId}-{$userId}-{$checksum}");
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

    public static function isValid(string $account, bool $strict = false): bool
    {
        $pattern = '/^[0-9A-F]{4}-[0-9A-F]{8}-([0-9A-F]{4}|'
            .self::LOOSELY_VALID_CHECKSUM
            .')$/i';

        if (1 === preg_match($pattern, $account)) {
            $checksum = strtoupper(substr($account, -4));

            if (self::LOOSELY_VALID_CHECKSUM === $checksum) {
                return !$strict;
            }

            $nodeId = substr($account, 0, 4);
            $userId = substr($account, 5, 8);
            $checksumComputed = AdsChecksumGenerator::getAccountChecksum(hexdec($nodeId), hexdec($userId));

            return $checksum === $checksumComputed;
        }

        return false;
    }

    public static function fromIncompleteString(string $string, bool $strict = true): AccountId
    {
        $pattern = '/^[0-9A-F]{4}-[0-9A-F]{8}$/i';

        if (1 === preg_match($pattern, $string)) {
            $nodeId = substr($string, 0, 4);
            $userId = substr($string, 5, 8);
            $checksum = $strict
                ? AdsChecksumGenerator::getAccountChecksum(hexdec($nodeId), hexdec($userId))
                : self::LOOSELY_VALID_CHECKSUM;

            return new self(strtoupper("{$nodeId}-{$userId}-{$checksum}"));
        }

        throw new InvalidArgumentException("'$string' is not a valid 'NODE-USER' string.");
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->account;
    }

    public function compareTo(Id $other): int
    {
        return 0;
    }

    public function equals(Id $other): bool
    {
        return $this->account === (string)$other;
    }
}
