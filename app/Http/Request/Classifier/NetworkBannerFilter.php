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

namespace Adshares\Adserver\Http\Request\Classifier;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Common\Domain\ValueObject\Exception\InvalidUuidException;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\ValueObject\Size;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;
use function sprintf;
use function urldecode;

class NetworkBannerFilter
{
    /** @var bool */
    private $approved;

    /** @var bool */
    private $rejected;

    /** @var bool */
    private $unclassified;

    /** @var array */
    private $sizes;

    /** @var string */
    private $type;

    /** @var int */
    private $userId;

    /** @var int|null */
    private $siteId;

    /** @var Uuid|null */
    private $networkBannerPublicId;

    /** @var string */
    private $landingUrl;

    public function __construct(Request $request, int $userId, ?int $siteId)
    {
        $this->approved = (bool)$request->get('approved', false);
        $this->rejected = (bool)$request->get('rejected', false);
        $this->unclassified = (bool)$request->get('unclassified', false);

        $this->sizes = json_decode($request->get('sizes', '[]'), true);
        $this->type = $request->get('type');

        $this->userId = $userId;
        $this->siteId = $siteId;

        try {
            $this->networkBannerPublicId =
                null !== ($bannerId = $request->get('banner_id')) ? Uuid::fromString($bannerId) : null;
        } catch (InvalidUuidException $exception) {
            throw new InvalidArgumentException(sprintf('[NetworkBannerFilter] %s', $exception->getMessage()));
        }

        $landingUrl = $request->get('landing_url');

        $this->landingUrl = $landingUrl ? urldecode($landingUrl) : null;

        $this->validate();
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

    public function getSizes(): array
    {
        return $this->sizes;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
    }

    public function getNetworkBannerPublicId(): ?Uuid
    {
        return $this->networkBannerPublicId;
    }

    public function getLandingUrl(): ?string
    {
        return $this->landingUrl;
    }

    private function validate(): void
    {
        $selectedStatusCount = 0;
        if ($this->approved) {
            ++$selectedStatusCount;
        }
        if ($this->rejected) {
            ++$selectedStatusCount;
        }
        if ($this->unclassified) {
            ++$selectedStatusCount;
        }
        if ($selectedStatusCount > 1) {
            throw new InvalidArgumentException('[NetworkBannerFilter] Too much statuses selected.');
        }

        if (null !== $this->type && !in_array($this->type, NetworkBanner::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('[NetworkBannerFilter] Invalid type (%s)', $this->type));
        }

        foreach ($this->sizes as $size) {
            if (!Size::isValid($size)) {
                throw new InvalidArgumentException(sprintf('[NetworkBannerFilter] Invalid size (%s)', $size));
            }
        }
    }
}
