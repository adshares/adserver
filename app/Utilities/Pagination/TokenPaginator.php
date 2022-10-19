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

namespace Adshares\Adserver\Utilities\Pagination;

use Closure;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;

class TokenPaginator extends CursorPaginator
{
    protected static Closure $currentPageResolver;
    protected int $currentPage;
    protected int $lastPage;
    protected int $total;

    public function __construct($items, $perPage, $cursor = null, $currentPage = null, array $options = [])
    {
        parent::__construct($items, $perPage, $cursor, $options);
        $this->total = $this->options['total'];
        $this->currentPage = $this->setCurrentPage($currentPage, $this->options['pageName']);
        $this->lastPage = max((int)ceil($this->total / $perPage), 1);
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items->toArray(),
            'first_page_url' => $this->buildUrl(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage,
            'last_page_url' => $this->buildUrl($this->lastPage),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'cursor' => $this->cursor()?->encode(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total,
        ];
    }

    public function cursor(): ?Cursor
    {
        return is_null($this->cursor) ? $this->getCursorForItem($this->items->first()) : $this->cursor;
    }

    public function nextPageUrl(): ?string
    {
        if ($this->currentPage >= $this->lastPage) {
            return null;
        }
        return $this->buildUrl($this->currentPage + 1);
    }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }
        return $this->buildUrl($this->currentPage - 1);
    }

    public function buildUrl(int $page): string
    {
        $cursor = $this->cursor();
        $parameters = is_null($cursor) ? [] : [$this->cursorName => $cursor->encode()];
        $parameters['page'] = $page;

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path()
            . (str_contains($this->path(), '?') ? '&' : '?')
            . Arr::query($parameters)
            . $this->buildFragment();
    }

    public function firstItem(): ?int
    {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    public function lastItem(): ?int
    {
        return count($this->items) > 0 ? $this->firstItem() + $this->count() - 1 : null;
    }

    public static function resolveCurrentPage($pageName = 'page', $default = 1)
    {
        if (isset(static::$currentPageResolver)) {
            return (int)call_user_func(static::$currentPageResolver, $pageName);
        }

        return $default;
    }

    public static function currentPageResolver(Closure $resolver): void
    {
        static::$currentPageResolver = $resolver;
    }

    protected function setCurrentPage(?int $currentPage, ?string $pageName): int
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage($pageName);

        return $this->isValidPageNumber($currentPage) ? (int)$currentPage : 1;
    }

    protected function isValidPageNumber($page): bool
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }
}
