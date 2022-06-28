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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'landing_url' => $this->faker->url,
            'time_start' => $this->faker->dateTimeThisMonth()->format(DATE_ATOM),
            'status' => Campaign::STATUS_DRAFT,
            'name' => $this->faker->word,
            'max_cpc' => '200000000000',
            'max_cpm' => '100000000000',
            'budget' => 100 * 1e11,
            'medium' => 'web',
            'vendor' => null,
            'targeting_excludes' => [],
            'targeting_requires' => [],
            'classification_status' => 0,
            'classification_tags' => null,
            'bid_strategy_uuid' => '00000000000000000000000000000000',
        ];
    }
}
