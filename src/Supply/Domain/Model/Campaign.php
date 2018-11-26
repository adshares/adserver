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
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Datetime;

final class Campaign
{
    const STATUS_PROCESSING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 2;

    /** @var Id */
    private $id;

    /** @var int */
    private $publisherId;

    /** @var string */
    private $landingUrl;

    /** @var ArrayCollection */
    private $banners;

    /** @var SourceCampaign */
    private $sourceCampaign;

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
        Id $publisherId,
        string $landingUrl,
        CampaignDate $campaignDate,
        array $banners,
        Budget $budget,
        SourceCampaign $sourceCampaign,
        int $status,
        array $targetingRequires = [],
        array $targetingExcludes = []
    ) {
        $this->id = $id;
        $this->demandCampaignId = $demandCampaignId;
        $this->publisherId = $publisherId;
        $this->landingUrl = $landingUrl;
        $this->budget = $budget;
        $this->sourceCampaign = $sourceCampaign;
        $this->targetingRequires = $targetingRequires;
        $this->targetingExcludes = $targetingExcludes;
        $this->status = $status;
        $this->campaignDate = $campaignDate;
        $this->banners = new ArrayCollection($banners);
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_DELETED;
    }

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
    }

    public function toArray(): array
    {
        return [
            'id' => (string)$this->id,
            'demand_campaign_id' => (string)$this->demandCampaignId,
            'publisher_id' => (string)$this->publisherId,
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
        ];
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

    public function getPublisherId(): string
    {
        return (string)$this->publisherId;
    }

    public function getDemandCampaignId(): string
    {
        return (string)$this->demandCampaignId;
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
