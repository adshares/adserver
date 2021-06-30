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

namespace Adshares\Common\Domain\ValueObject;

use Adshares\Common\Domain\Id;
use Adshares\Common\Domain\ValueObject\Exception\InvalidUuidException;

use function mt_rand;
use function sprintf;
use function str_replace;
use function substr;

final class Uuid implements Id
{
    /** @var string */
    private $id;

    public function __construct(string $value)
    {
        if (!self::isValid($value)) {
            throw new InvalidUuidException(sprintf('%s is not a valid UUID', $value));
        }

        $this->id = $value;
    }

    public static function isValid(string $uuid): bool
    {
        $pregMatch = preg_match(
            '/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i',
            $uuid
        );

        return 1 === $pregMatch;
    }

    public static function v4(): self
    {
        $id = sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        return new self($id);
    }

    public static function caseId(): self
    {
        $idV4 = (string)self::v4();
        $caseId = substr($idV4, 0, -2) . '00';

        return new self($caseId);
    }

    public static function zero(): self
    {
        $id = sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            // 32 bits for "time_low"
            0x0000,
            0x0000,
            // 16 bits for "time_mid"
            0x0000,
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            0x8000,
            // 48 bits for "node"
            0x0000,
            0x0000,
            0x0000
        );

        return new self($id);
    }

    public static function test(int $value): self
    {
        $id = sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            // 32 bits for "time_low"
            0xffff,
            0xffff,
            // 16 bits for "time_mid"
            0xffff,
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            0x0fff | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            0x3fff | 0x8000,
            // 48 bits for "node"
            0xffff,
            0xffff,
            $value
        );

        return new self($id);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->id;
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

        return $this->id === $other->id;
    }

    public function bin(): string
    {
        return hex2bin($this->hex());
    }

    public function hex(): string
    {
        return str_replace('-', '', $this->id);
    }
}
