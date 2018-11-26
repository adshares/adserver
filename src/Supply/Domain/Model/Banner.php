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

namespace Adshares\Supply\Domain\Model;

use Adshares\Common\Domain\Id;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerSizeException;
use Adshares\Supply\Domain\ValueObject\Size;

final class Banner
{
    const HTML_TYPE = 'html';
    const IMAGE_TYPE = 'image';

    const SUPPORTED_TYPES = [
        self::HTML_TYPE,
        self::IMAGE_TYPE,
    ];

    /** @var Id  */
    private $id;

    /** @var Campaign */
    private $campaign;

    /** @var BannerUrl */
    private $bannerUrl;

    /** @var string */
    private $type;

    /** @var Size */
    private $size;

    /** @var string */
    private $checksum;

    public function __construct(
        Campaign $campaign,
        Id $id,
        BannerUrl $bannerUrl,
        string $type,
        Size $size,
        string $checksum
    )
    {
        if (!in_array($type, self::SUPPORTED_TYPES)) {
            throw new UnsupportedBannerSizeException(sprintf(
                'Unsupported banner `%s` type. Only %s are allowed.',
                $type,
                implode(',', self::SUPPORTED_TYPES)
            ));
        }

        $this->id = $id;
        $this->campaign = $campaign;
        $this->bannerUrl = $bannerUrl;
        $this->type = $type;
        $this->size = $size;
        $this->checksum = $checksum;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'banner_url' => $this->bannerUrl,
            'type' => $this->type,
            'size' => (string)$this->size,
            'width' => $this->size->getWidth(),
            'height' => $this->size->getHeight(),
            'checksum' => $this->checksum,
            'serve_url' => $this->bannerUrl->getServeUrl(),
            'click_url' => $this->bannerUrl->getClickUrl(),
            'view_url' => $this->bannerUrl->getViewUrl(),
        ];
    }

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getCampaignId(): string
    {
        return (string)$this->campaign->getId();
    }

    public function getBannerUrl(): BannerUrl
    {
        return $this->bannerUrl;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getWidth(): int
    {
        return $this->size->getWidth();
    }

    public function getHeight(): int
    {
        return $this->size->getHeight();
    }

    public function getSize(): string
    {
        return (string)$this->size;
    }
}
