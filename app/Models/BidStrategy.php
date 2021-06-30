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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property string uuid
 * @property int user_id
 * @property string name
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property Collection bidStrategyDetails
 * @property Collection campaigns
 * @mixin Builder
 */
class BidStrategy extends Model
{
    use AutomateMutators;
    use BinHex;
    use SoftDeletes;

    public const ADMINISTRATOR_ID = 0;

    protected $table = 'bid_strategy';

    protected $appends = [
        'details',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected $fillable = [
        'name',
        'user_id',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $visible = [
        'details',
        'name',
        'uuid',
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

    public static function countByUserId(int $userId): int
    {
        return self::where('user_id', $userId)->count();
    }

    public static function fetchByPublicId(string $publicId): ?self
    {
        return self::where('uuid', hex2bin($publicId))->first();
    }

    public static function fetchForExport(DateTime $dateFrom, int $limit, int $offset = 0): Collection
    {
        return self::where('updated_at', '>=', $dateFrom)->limit($limit)->offset($offset)->get();
    }

    public static function fetchForUser(int $userId): Collection
    {
        return self::where('user_id', $userId)->get();
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
