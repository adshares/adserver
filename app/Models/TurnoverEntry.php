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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @mixin Builder
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property DateTimeInterface hour_timestamp
 * @property TurnoverEntryType type
 * @property int amount
 * @property string|null ads_address
 */
class TurnoverEntry extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use HasFactory;

    protected $casts = [
        'type' => TurnoverEntryType::class,
    ];

    protected $traitAutomate = [
        'ads_address' => 'AccountAddress',
    ];

    public static function increaseOrInsert(
        DateTimeInterface $hourTimestamp,
        TurnoverEntryType $type,
        int $amount,
        ?string $adsAddress = null,
    ): void {
        /** @var ?self $entry */
        $query = self::query()
            ->where('hour_timestamp', $hourTimestamp)
            ->where('type', $type->name);
        if (null === $adsAddress) {
            $query->whereNull('ads_address');
        } else {
            $query->where('ads_address', hex2bin(AdsUtils::decodeAddress($adsAddress)));
        }
        $entry = $query->first();

        if (null === $entry) {
            $entry = new self();
            $entry->hour_timestamp = $hourTimestamp;
            $entry->type = $type;
            $entry->amount = $amount;
            $entry->ads_address = $adsAddress;
        } else {
            $entry->amount += $amount;
        }

        $entry->save();
    }

    public static function fetchByHourTimestamp(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        return self::query()
            ->where('hour_timestamp', '>=', $from)
            ->where('hour_timestamp', '<=', $to)
            ->selectRaw('SUM(amount) as amount, type, ads_address')
            ->groupBy('type', 'ads_address')
            ->get();
    }
}
