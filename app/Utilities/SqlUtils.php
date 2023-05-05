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

namespace Adshares\Adserver\Utilities;

use Adshares\Adserver\Facades\DB;
use Closure;
use DateTimeZone;
use Illuminate\Database\QueryException;

class SqlUtils
{
    private const SQL_ERROR_INTEGRITY_CONSTRAINT_VIOLATION = 23000;

    private const SQL_ERROR_CODE_DUPLICATE_ENTRY = 1062;

    public static function isDuplicatedEntry(QueryException $queryException): bool
    {
        return self::SQL_ERROR_INTEGRITY_CONSTRAINT_VIOLATION === (int)$queryException->errorInfo[0]
            && self::SQL_ERROR_CODE_DUPLICATE_ENTRY === (int)$queryException->errorInfo[1];
    }

    public static function quotAndJoin(array $values): string
    {
        return join(
            ',',
            array_map(
                fn($value) => "'" . $value . "'",
                $values,
            ),
        );
    }

    public static function executeTimezoneAwareQuery(DateTimeZone $dateTimeZone, Closure $closure): mixed
    {
        $previousTimezone = self::setDbSessionTimezoneAndGetPrevious($dateTimeZone);
        $result = $closure();
        if ($previousTimezone) {
            self::setDbSessionTimeZone($previousTimezone);
        }
        return $result;
    }

    public static function setDbSessionTimezoneAndGetPrevious(DateTimeZone $dateTimeZone): string
    {
        $row = DB::selectOne('SELECT @@session.time_zone AS tz');
        self::setDbSessionTimeZone($dateTimeZone->getName());
        return $row->tz ?? '';
    }

    public static function setDbSessionTimeZone(string $timezone): void
    {
        DB::statement(sprintf("SET time_zone = '%s'", $timezone));
    }
}
