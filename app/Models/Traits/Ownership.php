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

namespace Adshares\Adserver\Models\Traits;

use Adshares\Adserver\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * @method ownedBy(User $user): Builder
 */
trait Ownership
{
    public static function bootOwnership(): void
    {
        static::addGlobalScope(new OwnershipScope(Auth::user()));
    }

    public function scopeOwnedBy(Builder $query, ?User $user): Builder
    {
        if (!$user || $user->isAdmin()) {
            return $query;
        }

        return $query->where('user_id', '=', $user->id);
    }
}
