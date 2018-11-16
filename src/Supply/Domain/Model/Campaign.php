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

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\ValueObject\SourceHost;
use Adshares\Supply\Domain\ValueObject\Budget;
use Datetime;

final class Campaign
{
    const STATUS_PROCESSING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 2;

    /** @var Uuid */
    private $id;

    /** @var int */
    private $userId;

    /** @var string */
    private $landingUrl;

    /** @var \DateTime */
    private $dateStart;

    /** @var \DateTime */
    private $dateEnd;

    /** @var ArrayCollection */
    private $banners;

    /** @var SourceHost */
    private $sourceHost;

    /** @var Budget */
    private $budget;

    private $targetingExcludes = [];

    private $targetingRequires = [];

    /** @var int */
    private $status;
    /** @var string */
    private $parentUuid;


    public function __construct(
        string $parentUuid,
        int $userId,
        string $landingUrl,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $banners,
        Budget $budget,
        SourceHost $sourceHost,
        int $status,
        array $targetingRequires = [],
        array $targetingExcludes = []
    )
    {
        $this->id = new Uuid();
        $this->parentUuid = $parentUuid;
        $this->userId = $userId;

        $this->landingUrl = $landingUrl;

        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;

        $this->banners = new ArrayCollection($banners);

        $this->budget = $budget;
        $this->sourceHost = $sourceHost;

        $this->targetingRequires = $targetingRequires;
        $this->targetingExcludes = $targetingExcludes;

        $this->status = $status;
    }

    public static function fromArray(array $data): self
    {
        $source = $data['source_host'];

        $budget = new Budget($data['budget'], $data['max_cpc'], $data['max_cpm']);
        $sourceHost = new SourceHost(
            $source['host'],
            $source['address'],
            $source['created_at'],
            $source['updated_at'],
            $source['version']
        );

        $arrayBanners = $data['banners'];
        $banners = [];

        $campaign = new self(
            $data['uuid'],
            $data['user_id'],
            $data['landing_url'],
            $data['date_start'],
            $data['date_start'],
            $banners,
            $budget,
            $sourceHost,
            self::STATUS_PROCESSING,
            $data['targeting_requires'],
            $data['targeting_excludes']
        );

        foreach ($arrayBanners as $banner) {
            $banners[] = Banner::fromArray($campaign, $banner);
        }

        $campaign->banners = new ArrayCollection($banners);

        return $campaign;
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

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getParentId()
    {
        return $this->parentUuid;
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

    public function getSourceCreatedAt(): DateTime
    {
        return $this->sourceHost->getCreatedAt();
    }

    public function getSourceUpdatedAt(): DateTime
    {
        return $this->sourceHost->getUpdatedAt();
    }

    public function getSourceVersion(): string
    {
        return $this->sourceHost->getVersion();
    }

    public function getMaxCpc(): int
    {
        return $this->budget->getMaxCpc();
    }

    public function getMaxCpm(): int
    {
        return $this->budget->getMaxCpm();
    }

    public function getBudget(): float
    {
        return $this->budget->getBudget();
    }

    public function getDateStart(): DateTime
    {
        return $this->dateStart;
    }

    public function getDateEnd(): DateTime
    {
        return $this->dateEnd;
    }
}
