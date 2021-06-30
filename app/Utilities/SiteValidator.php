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

namespace Adshares\Adserver\Utilities;

class SiteValidator
{
    private const DOMAIN_LENGTH_MAX = 255;

    private const DOMAIN_PATTERN = '~^
        (?![.])                                                  # do not allow domains starting with a dot
        ([\pL\pN\pS\-\_\.])+(\.?([\pL\pN]|xn\-\-[\pL\pN-]+)+\.?)
    $~ixu';

    private const URL_LENGTH_MAX = 1024;

    private const URL_PATTERN = '~^
        https?://                                                # protocol
        (?![.])                                                  # do not allow domains starting with a dot
        ([\pL\pN\pS\-\_\.])+(\.?([\pL\pN]|xn\-\-[\pL\pN-]+)+\.?) # a domain name
    $~ixu';

    /**
     * Checks if provided URL is valid. Accepts HTTP and HTTPS protocol only.
     *
     * @param mixed $value URL
     *
     * @return bool true, if URL is valid
     */
    public static function isUrlValid($value): bool
    {
        if (null === $value || '' === $value) {
            return false;
        }

        if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            return false;
        }

        $value = (string)$value;
        if ('' === $value || strlen($value) > self::URL_LENGTH_MAX) {
            return false;
        }

        return 1 === preg_match(self::URL_PATTERN, $value);
    }

    public static function isDomainValid($value): bool
    {
        if (null === $value || '' === $value) {
            return false;
        }

        if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            return false;
        }

        $value = (string)$value;
        if ('' === $value || strlen($value) > self::DOMAIN_LENGTH_MAX) {
            return false;
        }

        return 1 === preg_match(self::DOMAIN_PATTERN, $value);
    }
}
