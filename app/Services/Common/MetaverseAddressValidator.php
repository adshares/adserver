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

namespace Adshares\Adserver\Services\Common;

use Adshares\Adserver\Utilities\SiteUtils;
use Adshares\Adserver\ViewModel\MetaverseVendor;
use Adshares\Common\Exception\InvalidArgumentException;

class MetaverseAddressValidator
{
    private function __construct(private readonly ?MetaverseVendor $vendor)
    {
    }

    public static function fromVendor(?string $vendor): self
    {
        return new self(null === $vendor ? null : MetaverseVendor::tryFrom($vendor));
    }

    public function validateDomain(string $domain, bool $acceptBaseDomain = false): void
    {
        if (null === $this->vendor) {
            return;
        }

        if ($acceptBaseDomain && ($this->vendor->baseDomain() === $domain)) {
            return;
        }
        $this->validateUrl('https://' . $domain);
    }

    public function validateUrl(string $url): void
    {
        $methodName = sprintf('isValid%sUrl', $this->vendor?->name);
        if (method_exists(SiteUtils::class, $methodName) && !SiteUtils::$methodName($url)) {
            throw new InvalidArgumentException(sprintf('Invalid %s address %s', $this->vendor?->name, $url));
        }
    }
}
