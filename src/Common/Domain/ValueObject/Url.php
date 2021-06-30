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

use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\UrlInterface;

use function idn_to_utf8;

use const FILTER_VALIDATE_URL;
use const IDNA_ERROR_DISALLOWED;

final class Url implements UrlInterface
{
    /** @var string */
    private $idnUrl;

    public function __construct(string $url)
    {
        $idnUrl = idn_to_ascii($url, IDNA_ERROR_DISALLOWED, INTL_IDNA_VARIANT_UTS46);

        if (!filter_var($idnUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(sprintf('Given url (%s) is not correct.', $url));
        }

        $this->idnUrl = $idnUrl;
    }

    public function utf8(): string
    {
        return idn_to_utf8($this->idnUrl, IDNA_ERROR_DISALLOWED, INTL_IDNA_VARIANT_UTS46);
    }

    public function toString(): string
    {
        return $this->idnUrl;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
