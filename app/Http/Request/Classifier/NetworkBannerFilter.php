<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Http\Request\Classifier;

use Symfony\Component\HttpFoundation\Request;

class NetworkBannerFilter
{
    /** @var bool */
    private $approved;

    /** @var bool */
    private $rejected;

    /** @var bool */
    private $unclassified;

    /** @var int|null */
    private $width;

    /** @var int|null */
    private $height;

    /** @var int */
    private $userId;

    /** @var int|null */
    private $siteId;

    public function __construct(Request $request, int $userId, ?int $siteId)
    {
        $this->approved = (bool)$request->get('approved', false);
        $this->rejected = (bool)$request->get('rejected', false);
        $this->unclassified = (bool)$request->get('unclassified', false);

        $this->width = $request->get('width') ?: null;
        $this->height = $request->get('height') ?: null;

        $this->userId = $userId;
        $this->siteId = $siteId;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function isRejected(): bool
    {
        return $this->rejected;
    }

    public function isUnclassified(): bool
    {
        return $this->unclassified;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
    }
}
