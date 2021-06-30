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

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\InvalidUrlException;

final class BannerUrl
{
    /** @var string */
    private $serveUrl;

    /** @var string */
    private $clickUrl;

    /** @var string */
    private $viewUrl;

    public function __construct(string $serveUrl, string $clickUrl, string $viewUrl)
    {
        if (!$this->validate($serveUrl)) {
            throw new InvalidUrlException(sprintf(
                'Serve url value `%s` is invalid. It must be a valid URL.',
                $serveUrl
            ));
        }

        if (!$this->validate($clickUrl)) {
            throw new InvalidUrlException(sprintf(
                'Click url value `%s` is invalid. It must be a valid URL.',
                $clickUrl
            ));
        }

        if (!$this->validate($viewUrl)) {
            throw new InvalidUrlException(sprintf(
                'View url value `%s` is invalid. It must be a valid URL.',
                $viewUrl
            ));
        }

        $this->serveUrl = $serveUrl;
        $this->clickUrl = $clickUrl;
        $this->viewUrl = $viewUrl;
    }

    private function validate(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        $value = 'http:' . $value; // regarding to the fact that we also support addresses without schema

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        return false;
    }

    public function getServeUrl(): string
    {
        return $this->serveUrl;
    }

    public function getClickUrl(): string
    {
        return $this->clickUrl;
    }

    public function getViewUrl(): string
    {
        return $this->viewUrl;
    }
}
