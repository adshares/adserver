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

namespace Adshares\Adserver\Http\Response\Stats;

use Adshares\Adserver\Models\ReportMeta;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;

class ReportsListResponse implements Arrayable
{
    /** @var array */
    private $arr;

    public function __construct(Collection $reportMetas)
    {
        $this->arr = [];

        /** @var ReportMeta $reportMeta */
        foreach ($reportMetas as $reportMeta) {
            $this->arr[$reportMeta->type][] = [
                'id' => $reportMeta->uuid,
                'name' => sprintf('report_%s.xlsx', $reportMeta->name),
                'state' => $reportMeta->state,
            ];
        }
    }

    public function toArray(): array
    {
        return $this->arr;
    }
}
