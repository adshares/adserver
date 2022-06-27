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
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

class BannerFactory extends Factory
{
    public function definition(): array
    {
        $size = $this->faker->randomKey(Size::SIZE_INFOS);
        $type =
            Size::TYPE_POP === Size::SIZE_INFOS[$size]['type']
                ? Banner::TEXT_TYPE_DIRECT_LINK
                : $this->faker->randomElement([Banner::TEXT_TYPE_IMAGE, Banner::TEXT_TYPE_HTML]);
        switch ($type) {
            case Banner::TEXT_TYPE_HTML:
                $mime = 'text/html';
                break;
            case Banner::TEXT_TYPE_IMAGE:
                $mime = 'image/png';
                break;
            case Banner::TEXT_TYPE_DIRECT_LINK:
            default:
                $mime = 'text/plain';
                break;
        }

        return [
            'creative_contents' => $this->faker->sha1,
            'creative_type' => $type,
            'creative_mime' => $mime,
            'creative_size' => $size,
            'name' => $this->faker->word,
            'status' => Banner::STATUS_ACTIVE,
        ];
    }
}
