<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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
    private Id $id;
    private string $landingUrl;
    /** @var ArrayCollection|Banner[] */
    private ArrayCollection $banners;
    private SourceCampaign $sourceCampaign;
    private Budget $budget;
    private array $targetingExcludes;
    private array $targetingRequires;
    private Status $status;
    private string $medium;
    private ?string $vendor;
    private Id $demandCampaignId;
    private CampaignDate $campaignDate;

    public function __construct(
        Id $id,
        Id $demandCampaignId,
        string $landingUrl,
        CampaignDate $campaignDate,
        array $banners,
        Budget $budget,
        SourceCampaign $sourceCampaign,
        Status $status,
        string $medium,
        ?string $vendor,
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
        $this->medium = $medium;
        $this->vendor = $vendor;
        $this->campaignDate = $campaignDate;
        $this->banners = new ArrayCollection($banners);
    }

    public function delete(): void
    {
        $this->status = Status::deleted();

        foreach ($this->banners as $banner) {
            $banner->delete();
        }
    }

    public function activate(): void
    {
        $this->status = Status::active();

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
            'medium' => $this->medium,
            'vendor' => $this->vendor,
        ];
    }

    public function getStatus(): int
    {
        return $this->status->getStatus();
    }

    /**
     * @return ArrayCollection|Banner[]
     */
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

    public function getMedium(): string
    {
        return $this->medium;
    }

    public function getVendor(): ?string
    {
        return $this->vendor;
    }
}
