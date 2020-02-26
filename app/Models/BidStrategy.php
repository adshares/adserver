<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string category
 * @property float rank
 * @mixin Builder
 */
class BidStrategy extends Model
{
    public $timestamps = false;

    protected $table = 'bid_strategy';

    protected $visible = [];

    protected $fillable = [
        'category',
        'rank',
    ];

    public static function deleteAll(): int
    {
        return self::query()->delete();
    }

    public static function fetchAllWithReducedRank(): Collection
    {
        return self::where('rank', '<', 1)->get();
    }

    public static function fetchByCategory(string $category): ?self
    {
        return self::where('category', $category)->first();
    }

    public static function fetchByCategories(array $categories): Collection
    {
        return self::whereIn('category', $categories)->get();
    }

    public static function register(string $category, float $rank): self
    {
        $model = new self(
            [
                'category' => $category,
                'rank' => $rank,
            ]
        );
        $model->save();

        return $model;
    }
}
