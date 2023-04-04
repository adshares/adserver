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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversionDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory()->create(),
            'name' => $this->faker->word,
            'limit_type' => 'in_budget',
            'event_type' => 'Add to cart',
            'type' => ConversionDefinition::ADVANCED_TYPE,
            'value' => null,
            'is_value_mutable' => true,
            'is_repeatable' => true,
        ];
    }
}
