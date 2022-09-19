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

namespace Adshares\Config;

use Adshares\Common\Exception\RuntimeException;

final class UserRole
{
    public const ADVERTISER = 'advertiser';
    public const PUBLISHER = 'publisher';

    public function __construct()
    {
        throw new RuntimeException();
    }

    public static function cases(): array
    {
        return [
            self::ADVERTISER,
            self::PUBLISHER,
        ];
    }

    public static function validate(string $value): void
    {
        if (!in_array($value, self::cases())) {
            throw new RuntimeException(sprintf('Given value %s is not correct.', $value));
        }
    }
}
