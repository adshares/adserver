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

use RuntimeException;

final class AppMode
{
    public const INITIALIZATION = 'initialization';
    public const MAINTENANCE = 'maintenance';
    public const OPERATIONAL = 'operational';

    private function __construct()
    {
    }

    private static function cases(): array
    {
        return [
            self::INITIALIZATION,
            self::MAINTENANCE,
            self::OPERATIONAL,
        ];
    }

    public static function validate(string $value): void
    {
        if (!in_array($value, self::cases())) {
            throw new RuntimeException(sprintf('Given value %s is not correct.', $value));
        }
    }

    public static function getAppMode(): string
    {
        return 1 === config('app.setup') ? self::INITIALIZATION : self::OPERATIONAL;
    }
}
