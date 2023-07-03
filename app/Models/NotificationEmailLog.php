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

use Adshares\Adserver\ViewModel\NotificationEmailCategory;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon created_at
 * @property Carbon valid_until
 * @property int user_id
 * @property NotificationEmailCategory category
 * @property array properties
 * @mixin Builder
 */
class NotificationEmailLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;
    private const MAXIMAL_TIMESTAMP = '@2147483647';

    protected $casts = [
        'category' => NotificationEmailCategory::class,
    ];

    public static function register(
        int $userId,
        NotificationEmailCategory $category,
        ?DateTimeInterface $validUntil = null,
        array $properties = [],
    ): self {
        $log = new self();
        $log->user_id = $userId;
        $log->category = $category;
        $log->valid_until = $validUntil ??  new DateTimeImmutable(self::MAXIMAL_TIMESTAMP);
        $log->properties = $properties;
        $log->save();

        return $log;
    }

    public static function fetch(
        int $userId,
        NotificationEmailCategory $category,
        array $properties = [],
    ): ?self {
        $builder = self::query()
            ->where('user_id', $userId)
            ->where('category', $category)
            ->where('valid_until', '>', Carbon::now());
        if (empty($properties)) {
            return $builder->first();
        }

        foreach ($builder->get() as $log) {
            $logProperties = $log->properties;
            if (array_diff_assoc($logProperties, $properties) === array_diff_assoc($properties, $logProperties)) {
                return $log;
            }
        }
        return null;
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
