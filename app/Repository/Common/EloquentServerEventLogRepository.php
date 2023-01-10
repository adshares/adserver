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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Http\Requests\Filter\DateFilter;
use Adshares\Adserver\Http\Requests\Filter\Filter;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\ViewModel\ServerEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class EloquentServerEventLogRepository implements ServerEventLogRepository
{
    public function fetchServerEvents(
        ?FilterCollection $filters = null,
        ?int $perPage = null,
    ): CursorPaginator {
        $builder = ServerEventLog::orderBy('id', 'desc');

        if (null !== $filters) {
            foreach ($filters->getFilters() as $filter) {
                $builder = self::appendFilter($builder, $filter);
            }
        }

        return $builder->tokenPaginate($perPage);
    }

    public function fetchLatestServerEvents(
        ?FilterCollection $filters = null,
        ?int $perPage = null,
    ): CursorPaginator {
        $latestEvents = ServerEventLog::select(DB::raw('MAX(id) as max_id'))
            ->groupBy('type');

        if (null !== $filters) {
            foreach ($filters->getFilters() as $filter) {
                $latestEvents = self::appendFilter($latestEvents, $filter);
            }
        }

        return ServerEventLog::select(DB::raw('s.*'))
            ->from('server_event_logs AS s')
            ->orderBy('id', 'desc')
            ->joinSub($latestEvents, 'le', function ($join) {
                $join->on('s.id', '=', 'le.max_id');
            })
            ->tokenPaginate($perPage);
    }

    private static function appendFilter(Builder $builder, Filter $filter): Builder
    {
        switch ($filter->getName()) {
            case 'createdAt':
                if ($filter instanceof DateFilter) {
                    if (null !== ($from = $filter->getFrom())) {
                        $builder->where('created_at', '>=', $from);
                    }
                    if (null !== ($to = $filter->getTo())) {
                        $builder->where('created_at', '<=', $to);
                    }
                }
                break;
            case 'type':
                $builder->whereIn('type', $filter->getValues());
                break;
        }
        return $builder;
    }

    public function fetchServerEventTypes(): array
    {
        return ServerEventType::cases();
    }
}
