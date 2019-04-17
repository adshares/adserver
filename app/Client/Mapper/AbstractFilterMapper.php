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

use Illuminate\Support\Facades\Log;
use function is_array;
use function is_numeric;
use function json_encode;

abstract class AbstractFilterMapper
{
    private static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result += self::flatten($value, $prefix.$key.':');
            } elseif (is_numeric($key)) {
                $result[$prefix.$key][] = $value;
            } else {
                $result[$prefix.$key] = $value;
            }
        }

        return $result;
    }

    public static function generateNestedStructure(array $data): array
    {
        if (empty($data)) {
            Log::debug(
                sprintf(
                    '%s: %s',
                    __FUNCTION__,
                    json_encode($data))
            );
        }

        return self::flatten($data);
    }

    private static function isAssoc(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
