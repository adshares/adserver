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

use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

class SupplyBannerPlaceholderFactory extends Factory
{
    public function definition(): array
    {
        $size = $this->getSize();
        [$width, $height] = explode('x', $size);
        $content = UploadedFile::fake()
            ->image('test.png', $width, $height)
            ->size(100)
            ->getContent();
        return [
            'uuid' => $this->faker->uuid,
            'medium' => MediumName::Web->value,
            'size' => $size,
            'type' => 'image',
            'mime' => 'image/png',
            'content' => $content,
            'checksum' => sha1($content),
        ];
    }

    private function getSize(): string
    {
        $formats = (new DummyConfigurationRepository())->fetchMedium()->getFormats();
        foreach ($formats as $format) {
            if ('image' === $format->getType()) {
                return $this->faker->randomKey($format->getScopes());
            }
        }
        throw new RuntimeException('Format of type `image` is missing');
    }
}
