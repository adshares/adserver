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

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property int network_case_id
 * @property Carbon created_at
 * @mixin Builder
 */
class NetworkCaseClick extends Model
{
    public $timestamps = false;

    /** @var array */
    protected $dates = [
        'created_at',
    ];

    /** @var array */
    protected $fillable = [];

    /** @var array */
    protected $visible = [];

    public function networkCase(): BelongsTo
    {
        return $this->belongsTo(NetworkCase::class);
    }

    public static function fetchClicksToExport(
        int $idFrom,
        int $caseIdMax,
        int $limit,
        int $offset
    ): Collection {
        return self::where('id', '>=', $idFrom)
            ->where('network_case_id', '<=', $caseIdMax)
            ->take($limit)
            ->skip($offset)
            ->get();
    }
}
