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

use Adshares\Common\Exception\InvalidArgumentException;

enum CampaignStatus: int
{
    case Draft = 0;
    case Inactive = 1;
    case Active = 2;
    case Suspended = 3;

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'draft' => self::Draft,
            'inactive' => self::Inactive,
            'active' => self::Active,
            'suspended' => self::Suspended,
            default => throw new InvalidArgumentException('Unsupported value'),
        };
    }

    public function toString(): string
    {
        return strtolower($this->name);
    }
}
