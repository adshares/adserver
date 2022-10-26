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

namespace Adshares\Adserver\Http\Requests\Order;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OrderByCollection
{
    private const DEFAULT_DIRECTION = self::DIRECTION_DESCENDING;
    private const DIRECTION_ASCENDING = 'asc';
    private const DIRECTION_DESCENDING = 'desc';

    public function __construct(private readonly array $orderBy)
    {
    }

    public static function fromRequest(Request $request): ?self
    {
        $order = $request->query('orderBy');
        if (!$order) {
            return null;
        }
        if (!is_string($order)) {
            throw new UnprocessableEntityHttpException('OrderBy must be a string');
        }
        $orderBy = [];
        $orderParts = explode(',', $order);
        foreach ($orderParts as $orderPart) {
            if (false === ($index = strpos($orderPart, ':'))) {
                $orderBy[] = new OrderBy($orderPart, self::DEFAULT_DIRECTION);
            } else {
                $column = substr($orderPart, 0, $index);
                $direction = substr($orderPart, $index + 1);
                if (!in_array($direction, [self::DIRECTION_ASCENDING, self::DIRECTION_DESCENDING], true)) {
                    throw new UnprocessableEntityHttpException(sprintf('Invalid direction for %s', $column));
                }
                $orderBy[] = new OrderBy($column, $direction);
            }
        }

        return new self($orderBy);
    }

    /**
     * @return array|OrderBy[]
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }
}
