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

declare(strict_types=1);

namespace Adshares\Adserver\Utilities;

use function array_keys;
use function count;
use function is_array;
use function json_encode;
use function ksort;
use function range;
use function sort;

class ClassifierExternalKeywordsSerializer
{
    public static function serialize(array $data): string
    {
        $array = $data;
        self::sortRecursive($array);

        return json_encode($array);
    }

    private static function sortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }

        if (self::isAssocAndNotEmpty($array)) {
            ksort($array);
        } else {
            sort($array);
        }
    }

    private static function isAssocAndNotEmpty(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return ArrayUtils::isAssoc($arr);
    }
}
