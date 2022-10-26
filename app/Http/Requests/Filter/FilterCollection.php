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

namespace Adshares\Adserver\Http\Requests\Filter;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class FilterCollection
{
    private function __construct(private readonly array $filters)
    {
    }

    /**
     * @param Request $request
     * @param array<string, FilterType> $allowedFilters
     * @return FilterCollection|null
     */
    public static function fromRequest(Request $request, array $allowedFilters): ?self
    {
        $query = $request->query('filter');
        if (!$query) {
            return null;
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
                case FilterType::Date:
                    $dateFilter = new DateFilter($name);
                    if (null !== ($from = $queryValues['from'] ?? null)) {
                        if (!is_string($from)) {
                            throw new UnprocessableEntityHttpException('`from` must be a string in ISO 8601 format');
                        }
                        $from = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $from);
                        if (false === $from) {
                            throw new UnprocessableEntityHttpException('`from` must be in ISO 8601 format');
                        }
                        $dateFilter->setFrom($from);
                    }
                    if (null !== ($to = $queryValues['to'] ?? null)) {
                        if (!is_string($to)) {
                            throw new UnprocessableEntityHttpException('`to` must be a string in ISO 8601 format');
                        }
                        $to = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $to);
                        if (false === $to) {
                            throw new UnprocessableEntityHttpException('`to` must be in ISO 8601 format');
                        }
                        $dateFilter->setTo($to);
                    }
                    if (null !== $from && null !== $to && $from > $to) {
                        throw new UnprocessableEntityHttpException(
                            sprintf('Invalid time range for `%s` filter: `from` must be earlier than `to`', $name)
                        );
                    }
                    $filters[$name] = $dateFilter;
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

        return new self($filters);
    }

    /**
     * @return array<string, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilterByName(string $name): ?Filter
    {
        return $this->filters[$name] ?? null;
    }
}
