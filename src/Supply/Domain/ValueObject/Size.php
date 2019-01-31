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

declare(strict_types = 1);

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerSizeException;
use function in_array;

final class Size
{
    public const SUPPORTED_SIZES = [
        '300x250',
        '336x280',
        '728x90',
        '300x600',
        '320x100',
        #other
        '320x50',
        '468x60',
        '234x60',
        '120x600',
        '120x240',
        '160x600',
        '300x1050',
        '970x90',
        '970x250',
        '250x250',
        '200x200',
        '180x150',
        '125x125',
        #regional
        '240x400',# Most popular size in Russia.
        '980x120', # Most popular size in Sweden and Finland. Can also be used as a substitute in Norway.
        '250x360', # Second most popular size in Sweden.
        '930x180', # Very popular size in Denmark.
        '580x400', # Very popular size in Norway.
        #polish
        '750x100', # Very popular size in Poland.
        '750x200', # Most popular size in Poland.
        '750x300', # Third most popular size in Poland.
        # https://en.wikipedia.org/wiki/Web_banner
        '300x100',
        '120x90',
        '120x60',
        '88x31',
    ];

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    public function __construct(int $width, int $height)
    {
        if (!$this->isValid($width, $height)) {
            throw new UnsupportedBannerSizeException('Unsupported size value.');
        }

        $this->width = $width;
        $this->height = $height;
    }

    private function isValid(int $width, int $height): bool
    {
        return in_array($width.'x'.$height, self::SUPPORTED_SIZES);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function __toString(): string
    {
        return $this->width.'x'.$this->height;
    }
}
