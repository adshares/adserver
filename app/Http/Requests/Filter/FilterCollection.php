<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
            $filters[$name] = match ($allowedFilters[$name]) {
                FilterType::Bool => self::createBoolFilter($name, $queryValues),
                FilterType::Date => self::createDateFilter($name, $queryValues),
                FilterType::String => self::createStringFilter($name, $queryValues),
            };
        }

        return new self($filters);
    }

    private static function createBoolFilter(string $name, mixed $queryValues): Filter
    {
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
        return new BoolFilter($name, $value);
    }

    private static function createDateFilter(string $name, mixed $queryValues): Filter
    {
        if (null !== ($from = $queryValues['from'] ?? null)) {
            if (!is_string($from)) {
                throw new UnprocessableEntityHttpException('`from` must be a string in ISO 8601 format');
            }
            $from = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $from);
            if (false === $from) {
                throw new UnprocessableEntityHttpException('`from` must be in ISO 8601 format');
            }
        }
        if (null !== ($to = $queryValues['to'] ?? null)) {
            if (!is_string($to)) {
                throw new UnprocessableEntityHttpException('`to` must be a string in ISO 8601 format');
            }
            $to = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $to);
            if (false === $to) {
                throw new UnprocessableEntityHttpException('`to` must be in ISO 8601 format');
            }
        }
        if (null !== $from && null !== $to && $from > $to) {
            throw new UnprocessableEntityHttpException(
                sprintf('Invalid time range for `%s` filter: `from` must be earlier than `to`', $name)
            );
        }
        return new DateFilter($name, $from, $to);
    }

    private static function createStringFilter(string $name, mixed $queryValues): Filter
    {
        if (!is_array($queryValues)) {
            $queryValues = [$queryValues];
        }
        foreach ($queryValues as $value) {
            if (!is_string($value)) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Filtering by `%s` requires array of strings', $name)
                );
            }
            if (strlen($value) < 1) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Filtering by `%s` does not support empty string', $name)
                );
            }
        }
        return new StringFilter($name, $queryValues);
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
