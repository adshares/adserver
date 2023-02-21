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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ViewModel\ServerEventType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon created_at
 * @property ServerEventType type
 * @property array properties
 * @mixin Builder
 */
class ServerEventLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'created_at' => 'date:' . DateTimeInterface::ATOM,
        'type' => ServerEventType::class,
    ];

    protected $fillable = [
        'properties',
        'type',
    ];

    protected $visible = [
        'id',
        'created_at',
        'type',
        'properties',
    ];

    public static function register(ServerEventType $type, array $properties = []): void
    {
        $event = new self();
        $event->type = $type;
        $event->properties = $properties;
        $event->save();
    }

    public function getPropertiesAttribute(): array
    {
        return json_decode($this->attributes['properties'], true);
    }

    public function setPropertiesAttribute(array $properties): void
    {
        $this->attributes['properties'] = json_encode($properties, JSON_FORCE_OBJECT);
    }
}
