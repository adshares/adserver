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

use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkBannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid,
            'network_campaign_id' => $this->faker->randomDigit(),
            'serve_url' => $this->faker->url,
            'view_url' => $this->faker->url,
            'click_url' => $this->faker->url,
            'type' => 'image',
            'mime' => 'image/png',
            'size' => $this->faker->randomKey(Size::SIZE_INFOS),
            'checksum' => $this->faker->uuid,
        ];
    }
}
