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

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property int user_id
 * @property string name
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Collection bidStrategyDetails
 * @property Collection campaigns
 * @mixin Builder
 */
class BidStrategy extends Model
{
    public const ADMINISTRATOR_ID = 0;

    protected $table = 'bid_strategy';

    protected $fillable = [
        'name',
        'user_id',
    ];

    protected $visible = [
        'id',
        'details',
        'name',
    ];

    protected $appends = [
        'details',
    ];

    public static function register(string $name, int $userId): self
    {
        $model = new self(
            [
                'name' => $name,
                'user_id' => $userId,
            ]
        );
        $model->save();

        return $model;
    }

    public static function fetchById(int $id): ?self
    {
        return self::find($id);
    }

    public static function fetchForExport(DateTime $dateFrom): Collection
    {
        return self::where('updated_at', '>=', $dateFrom)->get();
    }

    public static function fetchForUser(int $userId): Collection
    {
        return self::whereIn('user_id', [self::ADMINISTRATOR_ID, $userId])->get();
    }

    public function bidStrategyDetails(): HasMany
    {
        return $this->hasMany(BidStrategyDetail::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function getDetailsAttribute(): Collection
    {
        return $this->bidStrategyDetails;
    }
}
