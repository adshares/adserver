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

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\Id;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\SourceHost;
use Datetime;

final class Campaign
{
    const STATUS_PROCESSING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 2;

    /** @var Id */
    private $id;

    /** @var int */
    private $userId;

    /** @var string */
    private $landingUrl;

    /** @var ArrayCollection */
    private $banners;

    /** @var SourceHost */
    private $sourceHost;

    /** @var Budget */
    private $budget;

    /** @var array  */
    private $targetingExcludes = [];

    /** @var array  */
    private $targetingRequires = [];

    /** @var int */
    private $status;

    /** @var Id */
    private $demandCampaignId;

    /** @var CampaignDate */
    private $campaignDate;

    public function __construct(
        Id $id,
        Id $demandCampaignId,
        int $userId,
        string $landingUrl,
        CampaignDate $campaignDate,
        array $banners,
        Budget $budget,
        SourceHost $sourceHost,
        int $status,
        array $targetingRequires = [],
        array $targetingExcludes = []
    ) {
        $this->id = $id;
        $this->demandCampaignId = $demandCampaignId;
        $this->userId = $userId;

        $this->landingUrl = $landingUrl;

        $this->banners = new ArrayCollection($banners);

        $this->budget = $budget;
        $this->sourceHost = $sourceHost;

        $this->targetingRequires = $targetingRequires;
        $this->targetingExcludes = $targetingExcludes;

        $this->status = $status;
        $this->campaignDate = $campaignDate;
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_DELETED;
    }

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBanners(): ArrayCollection
    {
        return $this->banners;
    }

    public function setBanners(ArrayCollection $banners): void
    {
        $this->banners = $banners;
    }

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getDemandCampaignId(): string
    {
        return (string)$this->demandCampaignId;
    }

    public function getLandingUrl(): string
    {
        return $this->landingUrl;
    }

    public function getSourceHost(): string
    {
        return $this->sourceHost->getHost();
    }

    public function getSourceAddress(): string
    {
        return $this->sourceHost->getAddress();
    }

    public function getCreatedAt(): DateTime
    {
        return $this->campaignDate->getCreatedAt();
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->campaignDate->getUpdatedAt();
    }

    public function getSourceVersion(): string
    {
        return $this->sourceHost->getVersion();
    }

    public function getMaxCpc(): float
    {
        return $this->budget->getMaxCpc();
    }

    public function getMaxCpm(): float
    {
        return $this->budget->getMaxCpm();
    }

    public function getBudget(): float
    {
        return $this->budget->getBudget();
    }

    public function getDateStart(): DateTime
    {
        return $this->campaignDate->getDateStart();
    }

    public function getDateEnd(): DateTime
    {
        return $this->campaignDate->getDateEnd();
    }

    public function getTargetingRequires(): array
    {
        return $this->targetingRequires;
    }

    public function getTargetingExcludes(): array
    {
        return $this->targetingExcludes;
    }
}
