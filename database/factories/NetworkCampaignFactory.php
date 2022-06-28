<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid,
            'source_address' => '0001-00000001-8B4E',
            'demand_campaign_id' => $this->faker->uuid,
            'publisher_id' => $this->faker->uuid,
            'source_host' => $this->faker->url,
            'source_version' => '0.1',
            'landing_url' => $this->faker->url,
            'max_cpc' => $this->faker->randomDigit(),
            'max_cpm' => $this->faker->randomDigit(),
            'budget' => $this->faker->randomDigit(),
            'date_start' => $this->faker->date('Y-m-d H:i:s'),
            'date_end' => $this->faker->date('Y-m-d H:i:s'),
        ];
    }
}
