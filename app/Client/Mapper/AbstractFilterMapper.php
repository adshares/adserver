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
use function implode;
use function is_array;
use function is_string;
use function json_encode;

abstract class AbstractFilterMapper
{
    public static function generateNestedStructure(array $data, array $fullPath = [], array &$values = []): array
    {
        if (empty($values)) {
            Log::debug(
                sprintf(
                    '%s: %s',
                    __FUNCTION__,
                    json_encode($data))
            );
        }

        foreach ($data as $key => $item) {
            if (is_array($item) && is_string($key)) {
                $fullPath[] = $key;
                self::generateNestedStructure($item, $fullPath, $values);

                $fullPath = array_slice($fullPath, 0, 1);

                if ($fullPath[0] === $key) {
                    $fullPath = [];
                }
            } else {
                $path = implode(':', $fullPath);

                if (!empty($path)) {
                    $values[$path] = (array)$data;
                }
            }
        }

        return $values;
    }
}
