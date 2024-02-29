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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int id
 * @property DateTimeInterface created_at
 * @property DateTimeInterface updated_at
 * @property DateTimeInterface|null deleted_at
 * @property string ads_address
 * @property boolean accepted
 * @mixin Builder
 */
class SspHost extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use HasFactory;
    use SoftDeletes;

    protected $traitAutomate = [
        'ads_address' => 'AccountAddress',
    ];

    public static function create(string $adsAddress, bool $accepted = false): void
    {
        $sspHost = new self();
        $sspHost->ads_address = $adsAddress;
        $sspHost->accepted = $accepted;
        $sspHost->save();
    }

    public static function fetchByAdsAddress(string $adsAddress): ?SspHost
    {
        return SspHost::where('ads_address', $adsAddress)->first();
    }

    public function accept(): void
    {
        $this->accepted = true;
        $this->save();
    }
}
