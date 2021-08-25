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

namespace Adshares\Adserver\Utilities;

use function parse_url;
use function strpos;
use function substr;

use const PHP_URL_HOST;

class DomainReader
{
    public static function domain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host && strpos($host, 'www.') === 0 ? substr($host, 4) : (string)$host;
    }

    public static function checkDomain(string $url, string $domain): bool
    {
        $urlDomain = self::domain($url);
        if ($urlDomain === $domain) {
            return true;
        }

        if (strpos($urlDomain, '.' . $domain) !== false) {
            return true;
        }

        return false;
    }
}
