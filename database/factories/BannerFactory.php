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

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

class BannerFactory extends Factory
{
    public function definition(): array
    {
        $format = $this->faker->randomElement((new DummyConfigurationRepository())->fetchMedium()->getFormats());
        return [
            'campaign_id' => Campaign::factory(),
            'creative_contents' => $this->faker->sha1,
            'creative_type' => $format->getType(),
            'creative_mime' => $this->faker->randomElement($format->getMimes()),
            'creative_size' => $this->faker->randomKey($format->getScopes()),
            'name' => $this->faker->word,
            'status' => Banner::STATUS_ACTIVE,
        ];
    }
}
