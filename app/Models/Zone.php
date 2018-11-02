<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE];
    public const ZONE_SIZE = [
        '728x90',
        '300x250',
        '336x280',
        '300x600',
        '320x100',
        '468x60',
        '234x60',
        '125x125',
        '120x600',
        '160x600',
        '180x150',
        '120x240',
        '200x200',
        '300x1050',
        '250x250',
        '320x50',
        '970x90',
        '970x250',
        '750x100',
        '750x200',
        '750x300',
    ];
    use SoftDeletes;
    protected $fillable = [
        'name',
        'width',
        'height',
        'status',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
