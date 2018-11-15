<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Supply\Domain\Model;

use Adshares\Common\Domain\Model\Uuid;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Size;

class Banner
{
    const HTML_TYPE = 'html';
    const IMAGE_TYPE = 'image';

    const SUPPORTED_TYPES = [
        self::HTML_TYPE,
        self::IMAGE_TYPE,
    ];

    private $id;

    /** @var Campaign */
    private $campaign;

    /** @var BannerUrl */
    private $bannerUrl;

    /** @var int */
    private $type;

    /** @var Size */
    private $size;

    public function __construct(Campaign $campaign, BannerUrl $bannerUrl, string $type, Size $size)
    {
        if (!in_array($type, self::SUPPORTED_TYPES)) {

        }

        $this->id = new Uuid();
        $this->campaign = $campaign;
        $this->bannerUrl = $bannerUrl;
        $this->type = $type;
        $this->size = $size;
    }

    public static function fromArray(Campaign $campaign, array $data): self
    {
        $bannerUrl = new BannerUrl($data['serve_url'], $data['click_url'], $data['view_url']);
        $size = new Size($data['width'], $data['height']);

        return new self(
            $campaign,
            $bannerUrl,
            $data['type'],
            $size
        );
    }

    public function getId(): string
    {
        return (string)$this->id->getId();
    }

    public function getBannerUrl(): BannerUrl
    {
        return $this->bannerUrl;
    }
}
