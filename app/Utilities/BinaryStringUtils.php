<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Utilities;

final class BinaryStringUtils
{
    public static function count(string $string): int
    {
        $count = 0;
        $length = strlen($string);
        $index = 0;

        while ($length >= 4) {
            $count += self::countSetBitsIn32BitsInteger(unpack('N', substr($string, $index, 4))[1]);

            $index += 4;
            $length -= 4;
        }
        while ($length > 0) {
            $count += self::countSetBitsIn32BitsInteger(ord(substr($string, $index, 1)));

            $index++;
            $length--;
        }

        return $count;
    }

    public static function and(string $stringA, string $stringB): string
    {
        return (string)($stringA & $stringB);
    }

    public static function not(string $string): string
    {
        return (string)~$string;
    }

    public static function or(string $stringA, string $stringB): string
    {
        return (string)($stringA | $stringB);
    }

    private static function countSetBitsIn32BitsInteger(int $value): int
    {
        $count = $value - (($value >> 1) & 0x55555555);
        $count = (($count >> 2) & 0x33333333) + ($count & 0x33333333);

        return ((((($count >> 4) + $count) & 0x0F0F0F0F) * 0x01010101) >> 24) & 0xFF;
    }
}
