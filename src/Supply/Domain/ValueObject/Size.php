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

declare(strict_types=1);

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerSizeException;
use function in_array;

final class Size
{
    const SUPPORTED_SIZES = [
        '120x240',
        '120x600',
        '125x125',
        '160x600',
        '180x150',
        '200x200',
        '234x60',
        '250x250',
        '300x1050',
        '300x250',
        '300x600',
        '320x100',
        '320x50',
        '336x280',
        '468x60',
        '728x90',
        '750x100',
        '750x200',
        '750x300',
        '970x250',
        '970x90',
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
        return in_array($width . 'x' . $height, self::SUPPORTED_SIZES);
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
