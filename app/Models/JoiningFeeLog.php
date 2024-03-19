<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property DateTimeInterface created_at
 * @property DateTimeInterface updated_at
 * @property string uuid
 * @property DateTimeInterface computed_at
 * @property string pay_to
 * @property int|null amount
 * @property int|null payment_id
 * @mixin Builder
 */
class JoiningFeeLog extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use HasFactory;

    protected $dates = [
        'computed_at',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    protected array $traitAutomate = [
        'uuid' => 'BinHex',
        'pay_to' => 'AccountAddress',
    ];

    public static function create(
        DateTimeInterface $computedAt,
        string $payTo,
        int $amount,
    ): void {
        $log = new self();
        $log->computed_at = $computedAt;
        $log->pay_to = $payTo;
        $log->amount = $amount;
        $log->save();
    }

    public static function fetchUnpaid(DateTimeInterface $from, ?DateTimeInterface $to, ?int $limit = null): Collection
    {
        $query = self::query()
            ->whereNotNull('amount')
            ->whereNotNull('pay_to')
            ->whereNull('payment_id')
            ->where('computed_at', '>=', $from);
        if (null !== $to) {
            $query->where('computed_at', '<', $to);
        }
        if (null !== $limit) {
            $query->limit($limit);
        }
        return $query->get();
    }

    public static function countPaid(array $paymentIds, string $payTo): int
    {
        return self::getJoiningFeeLogBuilder($paymentIds, $payTo)
            ->count();
    }

    public static function sumAmountPaid(array $paymentIds, string $payTo): int
    {
        return self::getJoiningFeeLogBuilder($paymentIds, $payTo)
            ->sum('amount');
    }

    private static function getJoiningFeeLogBuilder(array $paymentIds, string $payTo): Builder
    {
        return self::query()
            ->whereIn('payment_id', $paymentIds)
            ->where('pay_to', hex2bin($payTo));
    }
}
