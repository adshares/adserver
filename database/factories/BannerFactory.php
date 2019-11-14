<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Models\Banner;
use Adshares\Supply\Domain\ValueObject\Size;
use Faker\Generator as Faker;

$factory->define(Banner::class, function (Faker $faker) {
    return [
        'creative_contents' => $faker->sha1,
        'creative_type' => $faker->randomElement(['image', 'html']),
        'creative_sha1' => $faker->sha1,
        'creative_size' => $faker->randomElement(Size::SUPPORTED_SIZES),
        'name' => $faker->word,
        'status' => Banner::STATUS_ACTIVE,
    ];
});
