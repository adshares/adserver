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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property int user_id
 * @property int amount
 * @property string ads_address
 * @property int network_campaign_id
 */
class PublisherBoostLedgerEntry extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use HasFactory;

    protected array $traitAutomate = [
        'ads_address' => 'AccountAddress',
    ];

    public static function create(int $userId, int $amount, string $adsAddress, int $networkCampaignId): void
    {
        $entry = new self();
        $entry->user_id = $userId;
        $entry->amount = $amount;
        $entry->ads_address = $adsAddress;
        $entry->network_campaign_id = $networkCampaignId;
        $entry->save();
    }

    public static function getAvailableBoost(int $userId, string $adsAddress): int
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('ads_address', hex2bin($adsAddress))
            ->sum('amount');
    }
}
