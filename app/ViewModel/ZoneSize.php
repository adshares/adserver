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

namespace Adshares\Adserver\ViewModel;

use Adshares\Adserver\Models\Zone;
use Adshares\Supply\Domain\ValueObject\Size;

class ZoneSize
{
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly int $depth = Zone::DEFAULT_DEPTH,
    ) {
    }

    public static function fromArray(array $placement): self
    {
        return new self(
            (int)$placement['width'],
            (int)$placement['height'],
            (int)($placement['depth'] ?? Zone::DEFAULT_DEPTH)
        );
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function toString(): string
    {
        if ($this->depth > 0) {
            return 'cube';
        }
        return Size::fromDimensions($this->width, $this->height);
    }
}
