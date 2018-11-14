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

final class TransactionId implements Id
{
    /** @var string */
    private $transactionIdStringRepresentation;

    private function __construct(string $string)
    {
        $this->transactionIdStringRepresentation = $string;
    }

    public static function random(): TransactionId
    {
        $nodeId = str_pad(dechex(random_int(0, 2047)), 4, '0', STR_PAD_LEFT);
        $tranId = str_pad(dechex(random_int(0, 2047)), 8, '0', STR_PAD_LEFT);
        $mesgId = str_pad(dechex(random_int(0, 2047)), 4, '0', STR_PAD_LEFT);

        return self::fromString("{$nodeId}:{$tranId}:{$mesgId}");
    }

    public static function fromString(string $string): TransactionId
    {
        if (!self::isValid($string)) {
            throw new InvalidArgumentException("'$string' is NOT a VALID TransactionId representation.");
        }

        return new self(strtoupper($string));
    }

    public static function isValid(string $string): bool
    {
        return 1 === preg_match('/^[0-9A-F]{4}:[0-9A-F]{8}:[0-9A-F]{4}$/i', $string);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->transactionIdStringRepresentation;
    }

    public function equals(Id $other): bool
    {
        return $this->transactionIdStringRepresentation === $other->toString();
    }
}
