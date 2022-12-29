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
use Adshares\Adserver\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UploadedFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Banner::TEXT_TYPE_IMAGE,
            'medium' => 'web',
            'vendor' => null,
            'mime' => 'image/png',
            'scope' => '300x250',
            'content' => file_get_contents(base_path('tests/mock/Files/Banners/300x250.png')),
        ];
    }
}
