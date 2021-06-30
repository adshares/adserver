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

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\Id;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use Datetime;

final class Campaign
{
    /** @var Id */
    private $id;

    /** @var string */
    private $landingUrl;

    /** @var ArrayCollection */
    private $banners;

    /** @var SourceCampaign */
    private $sourceCampaign;

    /** @var Budget */
    private $budget;

    /** @var array */
    private $targetingExcludes = [];

    /** @var array */
    private $targetingRequires = [];

    /** @var Status */
    private $status;

    /** @var Id */
    private $demandCampaignId;

    /** @var CampaignDate */
    private $campaignDate;

    public function __construct(
        Id $id,
        Id $demandCampaignId,
        string $landingUrl,
        CampaignDate $campaignDate,
        array $banners,
        Budget $budget,
        SourceCampaign $sourceCampaign,
        Status $status,
        array $targetingRequires = [],
        array $targetingExcludes = []
    ) {
        $this->id = $id;
        $this->demandCampaignId = $demandCampaignId;
        $this->landingUrl = $landingUrl;
        $this->budget = $budget;
        $this->sourceCampaign = $sourceCampaign;
        $this->targetingRequires = $targetingRequires;
        $this->targetingExcludes = $targetingExcludes;
        $this->status = $status;
        $this->campaignDate = $campaignDate;
        $this->banners = new ArrayCollection($banners);
    }

    public function delete(): void
    {
        $this->status = Status::deleted();

        /** @var Banner $banner */
        foreach ($this->banners as $banner) {
            $banner->delete();
        }
    }

    public function activate(): void
    {
        $this->status = Status::active();

        /** @var Banner $banner */
        foreach ($this->banners as $banner) {
            $banner->activate();
        }
    }

    public function toArray(): array
    {
        return [
            'id' => (string)$this->id,
            'demand_campaign_id' => (string)$this->demandCampaignId,
            'landing_url' => $this->landingUrl,
            'max_cpc' => $this->budget->getMaxCpc(),
            'max_cpm' => $this->budget->getMaxCpm(),
            'budget' => $this->budget->getBudget(),
            'source_host' => $this->sourceCampaign->getHost(),
            'source_version' => $this->sourceCampaign->getVersion(),
            'source_address' => $this->sourceCampaign->getAddress(),
            'source_created_at' => $this->sourceCampaign->getCreatedAt(),
            'source_updated_at' => $this->sourceCampaign->getUpdatedAt(),
            'created_at' => $this->campaignDate->getCreatedAt(),
            'updated_at' => $this->campaignDate->getUpdatedAt(),
            'date_start' => $this->campaignDate->getDateStart(),
            'date_end' => $this->campaignDate->getDateEnd(),
            'targeting_requires' => $this->targetingRequires,
            'targeting_excludes' => $this->targetingExcludes,
            'status' => $this->status->getStatus(),
        ];
    }

    public function getStatus(): int
    {
        return $this->status->getStatus();
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

    public function getDemandCampaignId(): string
    {
        return (string)$this->demandCampaignId;
    }

    public function getDateStart(): DateTime
    {
        return $this->campaignDate->getDateStart();
    }

    public function getDateEnd(): ?DateTime
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

    public function getSourceAddress(): string
    {
        return $this->sourceCampaign->getAddress();
    }

    public function getBudget(): int
    {
        return $this->budget->getBudget();
    }

    public function getMaxCpc(): ?int
    {
        return $this->budget->getMaxCpc();
    }

    public function getMaxCpm(): ?int
    {
        return $this->budget->getMaxCpm();
    }
}
