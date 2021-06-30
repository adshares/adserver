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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

use function time;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property int status
 * @property int process_time_last
 * @property int process_count
 * @mixin Builder
 */
class EventLogsHourlyMeta extends Model
{
    public const STATUS_VALID = 0;

    public const STATUS_INVALID = 1;

    public const STATUS_ERROR = 2;

    private const MONTH = 30 * 24 * 60 * 60;

    /** @var array */
    protected $fillable = [
        'id',
        'status',
    ];

    /** @var array */
    protected $visible = [];

    public static function fetchInvalid(): Collection
    {
        return self::where('id', '>', time() - self::MONTH)->where('status', self::STATUS_INVALID)->get();
    }

    public static function invalidate(int $id): self
    {
        $meta = self::updateOrCreate(['id' => $id], ['status' => NetworkCaseLogsHourlyMeta::STATUS_INVALID]);
        $meta->touch();

        return $meta;
    }

    public function isActual(): bool
    {
        $currentUpdatedAt = self::find($this->id)->updated_at;

        return $this->updated_at->eq($currentUpdatedAt);
    }

    public function updateAfterProcessing(int $status, int $processTime): void
    {
        $this->status = $status;
        $this->process_time_last = $processTime;
        $this->process_count++;
        $this->save();
    }
}
