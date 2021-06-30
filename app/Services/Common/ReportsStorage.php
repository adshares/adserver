<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Services\Common;

use Illuminate\Support\Facades\Storage;

class ReportsStorage
{
    private const REPORTS_DIRECTORY = 'public/reports/';

    public static function delete(string $uuid): bool
    {
        return Storage::disk('local')->delete(self::REPORTS_DIRECTORY . $uuid);
    }

    public static function exists(string $uuid): bool
    {
        return Storage::disk('local')->exists(self::REPORTS_DIRECTORY . $uuid);
    }

    public static function getPath(): string
    {
        return Storage::disk('local')->path(self::REPORTS_DIRECTORY);
    }
}
