<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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
use function filter_var;

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
        if (!filter_var($serveUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException(sprintf(
                'Serve url value `%s` is invalid. It must be a valid URL.',
                $serveUrl
            ));
        }

        if (!filter_var($clickUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException(sprintf(
                'Click url value `%s` is invalid. It must be a valid URL.',
                $clickUrl
            ));
        }

        if (!filter_var($viewUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException(sprintf(
                'View url value `%s` is invalid. It must be a valid URL.',
                $viewUrl
            ));
        }

        $this->serveUrl = $serveUrl;
        $this->clickUrl = $clickUrl;
        $this->viewUrl = $viewUrl;
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
