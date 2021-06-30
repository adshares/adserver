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

namespace Adshares\Common\Domain\ValueObject;

use Adshares\Common\UrlInterface;

final class SecureUrl implements UrlInterface
{
    /** @var string */
    private $secureUrl;

    public function __construct(string $url)
    {
        $this->secureUrl = self::changeIfNeeded($url);
    }

    /** @deprecated Use: SecureUrl */
    public static function change(string $uri): string
    {
        if (config('app.banner_force_https') === false) {
            return $uri;
        }

        return str_replace('http:', 'https:', $uri);
    }

    private function changeIfNeeded(string $url): string
    {
        return self::change($url);
    }

    public function toString(): string
    {
        return $this->secureUrl;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
