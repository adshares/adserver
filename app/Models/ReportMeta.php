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
use Adshares\Adserver\Events\ReportMetaDeleting;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property int user_id
 * @property string uuid
 * @property string type
 * @property string state
 * @property string name
 * @mixin Builder
 */
class ReportMeta extends Model
{
    use AutomateMutators;
    use BinHex;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
        'deleting' => ReportMetaDeleting::class,
    ];

    public const NAME_LENGTH_MAX = 255;

    public const STATE_PREPARING = 'preparing';

    public const STATE_READY = 'ready';

    public const STATE_DELETED = 'deleted';

    public const TYPE_ADVERTISER = 'advertiser';

    public const TYPE_PUBLISHER = 'publisher';

    public const ALLOWED_TYPES = [
        self::TYPE_ADVERTISER,
        self::TYPE_PUBLISHER,
    ];

    public static function fetchByUserId(int $userId): Collection
    {
        return self::where('user_id', $userId)->get();
    }

    public static function fetchOlderThan(DateTime $dateTime): Collection
    {
        return self::where('updated_at', '<', $dateTime)->get();
    }

    public static function fetchByUserIdAndUuid(int $userId, string $uuid): ?self
    {
        return self::where('user_id', $userId)->where('uuid', hex2bin($uuid))->first();
    }

    public static function register(int $userId, string $name, string $type): self
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new InvalidArgumentException(sprintf('Invalid type (%s)', $type));
        }

        $reportMeta = new self(
            [
                'user_id' => $userId,
                'name' => substr($name, 0, self::NAME_LENGTH_MAX),
                'type' => $type,
            ]
        );
        $reportMeta->save();

        return $reportMeta;
    }

    public function ready(): self
    {
        $this->state = self::STATE_READY;
        $this->save();

        return $this;
    }
}
