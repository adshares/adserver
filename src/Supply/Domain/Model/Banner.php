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

namespace Adshares\Supply\Domain\Model;

use Adshares\Common\Domain\Id;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Classification;
use Adshares\Supply\Domain\ValueObject\Exception\UnsupportedBannerTypeException;
use Adshares\Supply\Domain\ValueObject\Status;

final class Banner
{
    private const TYPE_HTML = 'html';
    private const TYPE_IMAGE = 'image';
    private const TYPE_DIRECT_LINK = 'direct';

    private const SUPPORTED_TYPES = [
        self::TYPE_HTML,
        self::TYPE_IMAGE,
        self::TYPE_DIRECT_LINK,
    ];

    /** @var Id */
    private $id;

    /** @var Campaign */
    private $campaign;

    /** @var BannerUrl */
    private $bannerUrl;

    /** @var string */
    private $type;

    /** @var string */
    private $size;

    /** @var Status */
    private $status;

    /** @var string */
    private $checksum;

    /** @var Classification[] */
    private $classification;

    /** @var Id */
    private $demandBannerId;

    public function __construct(
        Campaign $campaign,
        Id $id,
        Id $demandBannerId,
        BannerUrl $bannerUrl,
        string $type,
        string $size,
        string $checksum,
        Status $status,
        ?array $classification = []
    ) {
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new UnsupportedBannerTypeException(sprintf(
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
        $this->demandBannerId = $demandBannerId;
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
            $classification[$classificationItem->getClassifier()] = $classificationItem->getKeywords();
        }

        return [
            'id' => $this->getId(),
            'demand_banner_id' => $this->getDemandBannerId(),
            'type' => $this->getType(),
            'size' => $this->size,
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

    public function getDemandBannerId(): string
    {
        return (string)$this->demandBannerId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCampaignId(): string
    {
        return $this->campaign->getId();
    }

    public function getSize(): string
    {
        return $this->size;
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
