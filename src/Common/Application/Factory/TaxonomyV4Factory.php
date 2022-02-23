<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\TaxonomyV4;

use function GuzzleHttp\json_decode;

final class TaxonomyV4Factory
{
    public static function fromJson(string $json): TaxonomyV4
    {
        $data = json_decode($json, true);
        $data = self::replaceParameters($data);

        return TaxonomyV4::fromArray($data);
    }

    private static function replaceParameters($data): array
    {
        if (array_key_exists('parameters', $data) && is_array($data['parameters'])) {
            $parameters = $data['parameters'];
            unset($data['parameters']);

            array_walk_recursive(
                $data,
                function (&$value, $key, $parameters) {
                    if (is_string($value) && substr($value, 0, 1) === '@') {
                        if (array_key_exists($value, $parameters)) {
                            $value = $parameters[$value];
                        }
                    }
                },
                $parameters
            );
        }
        return $data;
    }
}
