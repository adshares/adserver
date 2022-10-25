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

namespace Adshares\Adserver\Http\Request\Filter;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class FilterFactory
{
    /**
     * @param Request $request
     * @param array<string, FilterType> $allowedFilters
     * @return Filter[]
     */
    public static function fromRequest(Request $request, array $allowedFilters): array
    {
        $query = $request->query('filter');
        if (!$query) {
            return [];
        }
        if (!is_array($query)) {
            throw new UnprocessableEntityHttpException('Filter must be an array');
        }
        $filters = [];
        foreach ($query as $name => $queryValues) {
            if (!isset($allowedFilters[$name])) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Filtering by `%s` is not supported', $name)
                );
            }
            switch ($allowedFilters[$name]) {
                case FilterType::Bool:
                    if (
                        null === ($value = filter_var(
                            $queryValues,
                            FILTER_VALIDATE_BOOLEAN,
                            FILTER_NULL_ON_FAILURE
                        ))
                    ) {
                        throw new UnprocessableEntityHttpException(
                            sprintf('Filtering by `%s` requires boolean', $name)
                        );
                    }
                    $filters[$name] = new BoolFilter($name, $value);
                    break;
                case FilterType::String:
                    if (!$queryValues) {
                        throw new UnprocessableEntityHttpException(
                            sprintf('Filtering by `%s` requires at least one string value', $name)
                        );
                    }
                    if (!is_array($queryValues)) {
                        $queryValues = [$queryValues];
                    }
                    foreach ($queryValues as $value) {
                        if (!is_string($value)) {
                            throw new UnprocessableEntityHttpException(
                                sprintf('Filtering by `%s` requires array of strings', $name)
                            );
                        }
                    }
                    $filters[$name] = new StringFilter($name, $queryValues);
                    break;
            }
        }

        return $filters;
    }
}
