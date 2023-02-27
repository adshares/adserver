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

namespace Adshares\Config;

use Adshares\Common\Exception\RuntimeException;

final class AppMode
{
    public const INITIALIZATION = 'initialization';
    public const MAINTENANCE = 'maintenance';
    public const OPERATIONAL = 'operational';

    public function __construct()
    {
        throw new RuntimeException();
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
        if (1 === config('app.is_maintenance')) {
            return self::MAINTENANCE;
        }
        return 1 === config('app.setup') ? self::INITIALIZATION : self::OPERATIONAL;
    }
}
