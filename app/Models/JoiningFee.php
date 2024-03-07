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

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property DateTimeInterface created_at
 * @property DateTimeInterface updated_at
 * @property DateTimeInterface|null deleted_at
 * @property string ads_address
 * @property int total_amount
 * @property int left_amount
 * @mixin Builder
 */
class JoiningFee extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use HasFactory;

    protected array $traitAutomate = [
        'ads_address' => 'AccountAddress',
    ];

    public static function create(string $adsAddress, int $amount): void
    {
        $joiningFee = new self();
        $joiningFee->ads_address = $adsAddress;
        $joiningFee->total_amount = $amount;
        $joiningFee->left_amount = $amount;
        $joiningFee->save();
    }

    public static function fetchJoiningFeesForAllocation(): Collection
    {
        return self::query()
            ->get();
    }

    public function getAllocationAmount(DateTimeInterface $date): int
    {
        $difference = $date->getTimestamp() - $this->created_at->getTimestamp();
        if ($difference <= 0) {
            return 0;
        }
        $difference /= 3600;
        $allocationPeriodInHours = config('app.joining_fee_allocation_period_in_hours');
        $period = (int)ceil($difference / $allocationPeriodInHours);
        $amountPerPeriod = $this->total_amount / (2 ** $period);
        $amount = (int)floor($amountPerPeriod / $allocationPeriodInHours);
        return min($amount, $this->left_amount);
    }
}
