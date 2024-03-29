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
use Adshares\Adserver\Utilities\AdsUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int id
 * @property DateTimeInterface created_at
 * @property DateTimeInterface updated_at
 * @property DateTimeInterface|null deleted_at
 * @property int user_id
 * @property int amount
 * @property int amount_left
 * @property string ads_address
 */
class PublisherBoostLedgerEntry extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use HasFactory;
    use SoftDeletes;

    protected array $traitAutomate = [
        'ads_address' => 'AccountAddress',
    ];

    public static function create(int $userId, int $amount, string $adsAddress): void
    {
        $entry = new self();
        $entry->user_id = $userId;
        $entry->amount = $amount;
        $entry->amount_left = $amount;
        $entry->ads_address = $adsAddress;
        $entry->save();
    }

    public static function deleteOutdated(): int
    {
        $date = new DateTimeImmutable('-3 months');
        $builder = self::query()
            ->where('created_at', '<', $date);

        $amount = $builder->sum('amount_left');
        $builder->delete();

        return $amount;
    }

    public static function getAvailableBoost(int $userId, string $adsAddress): int
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('ads_address', hex2bin(AdsUtils::decodeAddress($adsAddress)))
            ->sum('amount_left');
    }

    public static function withdraw(int $userId, string $adsAddress, int $amount): void
    {
        self::query()
            ->where('user_id', $userId)
            ->where('ads_address', hex2bin(AdsUtils::decodeAddress($adsAddress)))
            ->orderBy('created_at')
            ->chunk(20, static function (Collection $entries) use ($amount) {
                /** @var PublisherBoostLedgerEntry $entry */
                foreach ($entries as $entry) {
                    if ($amount <= 0) {
                        break;
                    }
                    $amountToWithdraw = min($amount, $entry->amount_left);
                    $amount -= $amountToWithdraw;
                    $entry->amount_left -= $amountToWithdraw;
                    $entry->save();
                }
                return $amount > 0;
            });
    }
}
