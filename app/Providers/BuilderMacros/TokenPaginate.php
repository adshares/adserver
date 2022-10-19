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

namespace Adshares\Adserver\Providers\BuilderMacros;

use Adshares\Adserver\Utilities\Pagination\TokenPaginator;
use Closure;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

class TokenPaginate
{
    public function __invoke(): Closure
    {
        return function (
            int|null $perPage = null,
            array|string $columns = ['*'],
            string $cursorName = 'cursor',
            Cursor|string|null $cursor = null,
            $pageName = 'page',
            $page = null,
        ): CursorPaginator {
            $perPage = (int)($perPage ?: $this->model->getPerPage());
            $page = $page ?: Paginator::resolveCurrentPage($pageName);

            $total = $this->toBase()->getCountForPagination();

            if (!$cursor instanceof Cursor) {
                $cursor = is_string($cursor)
                    ? Cursor::fromEncoded($cursor)
                    : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
            }

            $orders = $this->ensureOrderForCursorPagination(!is_null($cursor) && $cursor->pointsToPreviousItems());

            if (!is_null($cursor)) {
                $addCursorConditions = function (
                    self $builder,
                    $previousColumn,
                    $i,
                ) use (
                    &$addCursorConditions,
                    $cursor,
                    $orders
                ) {
                    $unionBuilders = isset($builder->unions) ? collect($builder->unions)->pluck('query') : collect();

                    if (!is_null($previousColumn)) {
                        $originalColumn = $this->getOriginalColumnNameForCursorPagination($this, $previousColumn);

                        $builder->where(
                            Str::contains($originalColumn, ['(', ')']) ? new Expression(
                                $originalColumn
                            ) : $originalColumn,
                            '=',
                            $cursor->parameter($previousColumn)
                        );

                        $unionBuilders->each(function ($unionBuilder) use ($previousColumn, $cursor) {
                            $unionBuilder->where(
                                $this->getOriginalColumnNameForCursorPagination($this, $previousColumn),
                                '=',
                                $cursor->parameter($previousColumn)
                            );

                            $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                        });
                    }

                    $builder->where(
                        function (self $builder) use ($addCursorConditions, $cursor, $orders, $i, $unionBuilders) {
                            ['column' => $column, 'direction' => $direction] = $orders[$i];

                            $originalColumn = $this->getOriginalColumnNameForCursorPagination($this, $column);

                            $builder->where(
                                Str::contains($originalColumn, ['(', ')']) ? new Expression(
                                    $originalColumn
                                ) : $originalColumn,
                                $direction === 'asc' ? '>=' : '<=',
                                $cursor->parameter($column)
                            );

                            if ($i < $orders->count() - 1) {
                                $builder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i) {
                                    $addCursorConditions($builder, $column, $i + 1);
                                });
                            }

                            $unionBuilders->each(
                                function ($unionBuilder) use (
                                    $column,
                                    $direction,
                                    $cursor,
                                    $i,
                                    $orders,
                                    $addCursorConditions
                                ) {
                                    $unionBuilder->where(
                                        function ($unionBuilder) use (
                                            $column,
                                            $direction,
                                            $cursor,
                                            $i,
                                            $orders,
                                            $addCursorConditions
                                        ) {
                                            $unionBuilder->where(
                                                $this->getOriginalColumnNameForCursorPagination($this, $column),
                                                $direction === 'asc' ? '>=' : '<=',
                                                $cursor->parameter($column)
                                            );

                                            if ($i < $orders->count() - 1) {
                                                $unionBuilder->orWhere(
                                                    function (self $builder) use ($addCursorConditions, $column, $i) {
                                                        $addCursorConditions($builder, $column, $i + 1);
                                                    }
                                                );
                                            }

                                            $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                                        }
                                    );
                                }
                            );
                        }
                    );
                };

                $addCursorConditions($this, null, 0);
            }

            $items = $total
                ? $this->forPage($page, $perPage)->get($columns)
                : $this->model->newCollection();
            return new TokenPaginator($items, $perPage, $cursor, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'cursorName' => $cursorName,
                'pageName' => $pageName,
                'parameters' => $orders->pluck('column')->toArray(),
                'total' => $total,
            ]);
        };
    }
}
