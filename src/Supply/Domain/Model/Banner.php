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

declare(strict_types = 1);

namespace Adshares\Supply\Domain\Model;

use Adshares\Common\Domain\Id;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Classification;
use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerSizeException;
use Adshares\Supply\Domain\ValueObject\Size;
use Adshares\Supply\Domain\ValueObject\Status;

final class Banner
{
    const HTML_TYPE = 'html';
    const IMAGE_TYPE = 'image';

    const SUPPORTED_TYPES = [
        self::HTML_TYPE,
        self::IMAGE_TYPE,
    ];

    /** @var Id */
    private $id;

    /** @var Campaign */
    private $campaign;

    /** @var BannerUrl */
    private $bannerUrl;

    /** @var string */
    private $type;

    /** @var Size */
    private $size;

    /** @var Status */
    private $status;

    /** @var string */
    private $checksum;

    /** @var Classification[] */
    private $classification;

    public function __construct(
        Campaign $campaign,
        Id $id,
        BannerUrl $bannerUrl,
        string $type,
        Size $size,
        string $checksum,
        Status $status,
        ?array $classification = []
    ) {
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
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
        $this->status = $status;
        $this->checksum = $checksum;
        $this->classification = $classification;
    }

    public function activate(): void
    {
        $this->status = Status::active();
    }

    public function delete(): void
    {
        $this->status = Status::deleted();
    }

    public function classify(Classification $classification): void
    {
        $this->classification[] = $classification;
    }

    public function removeClassification(Classification $classification): void
    {
        foreach ($this->classification as $key => $item) {
            if ($classification->equals($item)) {
                unset($this->classification[$key]);
            }
        }
    }

    public function unclassified(): void
    {
        $this->classification = [];
    }

    public function toArray(): array
    {
        $classification = [];
        /** @var Classification $classification */
        foreach ($this->classification as $classificationItem) {
            $classification[] = $classificationItem->toArray();
        }

        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'size' => (string)$this->size,
            'width' => $this->size->getWidth(),
            'height' => $this->size->getHeight(),
            'checksum' => $this->checksum,
            'serve_url' => $this->bannerUrl->getServeUrl(),
            'click_url' => $this->bannerUrl->getClickUrl(),
            'view_url' => $this->bannerUrl->getViewUrl(),
            'status' => $this->status->getStatus(),
            'classification' => $classification,
        ];
    }

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCampaignId(): string
    {
        return $this->campaign->getId();
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

    public function getStatus(): int
    {
        return $this->status->getStatus();
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getClassification(): ?array
    {
        return $this->classification;
    }
}
