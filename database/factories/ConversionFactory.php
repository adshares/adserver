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

declare(strict_types=1);

namespace Database\Factories;

use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Domain\ValueObject\AccountId;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid,
            'created_at' => new DateTimeImmutable(),
            'updated_at' => new DateTimeImmutable(),
            'event_logs_id' => EventLog::factory()->create(['payment_id' => 0]),
            'case_id' => $this->faker->uuid,
            'group_id' => $this->faker->uuid,
            'conversion_definition_id' => ConversionDefinition::factory()->create(),
            'value' => 1e11,
            'event_value_currency' => 1e11,
            'weight' => 1,
            'pay_to' => AccountId::fromIncompleteString('0001-00000001'),

        ];
    }
}
