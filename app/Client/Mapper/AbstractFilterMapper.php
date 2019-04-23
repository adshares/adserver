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

namespace Adshares\Adserver\Client\Mapper;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_numeric;
use function str_ireplace;
use function stripos;

abstract class AbstractFilterMapper
{
    private static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (self::isAssoc($value)) {
                    if (self::allKeysAreNumeric($value)) {
                        $result[$prefix.$key] = array_values($value);
                    } else {
                        $result += self::flatten($value, $prefix.$key.':');
                    }
                } else {
                    $result[$prefix.$key] = $value;
                }
            } elseif ($value) {
                $result[$prefix.$key] = [$value];
            }
        }

        return array_filter($result);
    }

    public static function generateNestedStructure(array $data): array
    {
        return self::modifyDomain(self::flatten($data));
    }

    private static function isAssoc(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private static function allKeysAreNumeric(array $value): bool
    {
        return empty(array_filter(
            array_keys($value),
            static function ($key) {
                return !is_numeric($key);
            }
        ));
    }

    private static function modifyDomain(array $flattened): array
    {
        $condition = static function (string $key): bool {
            return $key === 'domain'
                || (stripos($key, ':domain') !== false && stripos($key, ':domain:') === false);
        };

        $replaceCallback = static function (string $value): string {
            return str_ireplace(['http:', 'https:', '//www.'], ['', '', '//'], $value);
        };

        $callback = static function (array $items, string $key) use ($replaceCallback, $condition): array {
            if ($condition($key)) {
                return array_map($replaceCallback, $items);
            }

            return $items;
        };

        $mapped = array_map($callback, $flattened, array_keys($flattened));

        return $mapped;
    }
}
