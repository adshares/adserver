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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\Serialize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 */
class UserSettings extends Model
{
    use AutomateMutators;
    use Serialize;

    private const NOTIFY = 'notify';
    private const EMAIL = 'email';

    public static $default_notifications = [
        'billing' => [self::EMAIL => true, self::NOTIFY => true],
        'maintenance' => [self::EMAIL => true, self::NOTIFY => true],
        'newsletter' => [self::EMAIL => true, self::NOTIFY => true],
        'tips' => [self::EMAIL => true, self::NOTIFY => true],
        'offers' => [self::EMAIL => true, self::NOTIFY => true],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'type',
        'payload',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Custom table name.
     *
     * @var string
     */
    protected $table = 'users_settings';

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'payload' => 'Serialize',
    ];
}
