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

use function implode;
use function is_string;

trait FilterMapper
{
    public static function generateNestedStructure(array $data, array $keyword = []): array
    {
        $values = [];
        foreach ($data as $key => $item) {
            if (is_array($item)) {
                $keyword[] = $key;
                $filter = [
                    'keyword' => $key,
                    'filter' => [
                        'args' => self::generateNestedStructure($item, $keyword),
                        'type' => self::chooseFilterType($item),
                    ],
                ];

                $values[] = $filter;
                $keyword = [];
            } else {
                $filter = [
                    'keyword' => implode('_', $keyword),
                    'filter' => [
                        'args' => $item,
                        'type' => self::FILTER_EQUAL,
                    ],
                ];

                $values[] = $filter;
            }
        }

        return $values;
    }

    private static function chooseFilterType($item): string
    {
        $isMulti = function ($data) {
            foreach ($data as $key => $value) {
                if (!is_string($key)) {
                    return false;
                }
            }

            return true;
        };

        if ($isMulti($item)) {
            return self::FILTER_AND;
        }

        return self::FILTER_OR;
    }
}
