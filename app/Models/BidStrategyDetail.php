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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property int bid_strategy_id
 * @property string category
 * @property float rank
 * @property BidStrategy bidStrategy
 * @property Carbon|null deleted_at
 * @mixin Builder
 */
class BidStrategyDetail extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $casts = [
        'rank' => 'float',
    ];

    protected $fillable = [
        'category',
        'rank',
    ];

    protected $visible = [
        'category',
        'rank',
    ];

    protected $touches = [
        'bidStrategy',
    ];

    public static function create(string $category, float $rank): self
    {
        return new self(
            [
                'category' => $category,
                'rank' => $rank,
            ]
        );
    }

    public function bidStrategy(): BelongsTo
    {
        return $this->belongsTo(BidStrategy::class);
    }
}
