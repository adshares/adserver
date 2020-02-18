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
        for ($index = 0; $index < $length; $index++) {
            $count += self::countSetBitsIn32BitsInteger(ord(substr($string, $index, 1)));
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
        $value = $value - (($value >> 1) & 0x55555555);
        $value = ($value & 0x33333333) + (($value >> 2) & 0x33333333);

        return (((($value + ($value >> 4)) & 0xF0F0F0F) * 0x1010101) >> 24);
    }
}
