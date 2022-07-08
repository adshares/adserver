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

use Adshares\Adserver\Models\Site;
use Adshares\Common\Application\Service\AdUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'domain' => 'example.com',
            'url' => 'https://example.com',
            'medium' => 'web',
            'vendor' => null,
            'primary_language' => $this->faker->languageCode,
            'status' => Site::STATUS_ACTIVE,
            'rank' => 1,
            'info' => AdUser::PAGE_INFO_OK,
            'categories' => ['unknown'],
            'only_accepted_banners' => 0,
        ];
    }
}
