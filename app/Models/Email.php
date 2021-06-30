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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int id
 * @property int created_at
 * @property string subject
 * @property string body
 */
class Email extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'subject',
        'body',
    ];

    protected $visible = [
        'created_at',
        'subject',
        'body',
    ];

    public static function create(string $subject, string $body): void
    {
        (new self(
            [
                'subject' => $subject,
                'body' => $body,
            ]
        ))->save();
    }

    public static function fetchById(int $id): ?self
    {
        return self::find($id);
    }
}
