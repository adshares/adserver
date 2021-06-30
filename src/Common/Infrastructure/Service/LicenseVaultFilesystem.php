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

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Common\Application\Service\LicenseDecoder;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Domain\ValueObject\License;
use Adshares\Common\Exception\RuntimeException;

use function file_exists;
use function file_get_contents;
use function file_put_contents;

class LicenseVaultFilesystem implements LicenseVault
{
    /** @var string */
    private $path;
    /** @var LicenseDecoder */
    private $licenseDecoder;

    public function __construct(string $path, LicenseDecoder $licenseDecoder)
    {
        $this->path = $path;
        $this->licenseDecoder = $licenseDecoder;
    }

    public function read(): License
    {
        if (!file_exists($this->path)) {
            throw new RuntimeException('License not found.');
        }

        $content = file_get_contents($this->path);

        return $this->licenseDecoder->decode($content);
    }

    public function store(string $license): void
    {
        file_put_contents($this->path, $license);
    }
}
