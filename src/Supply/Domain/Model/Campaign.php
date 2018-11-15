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
use Adshares\Common\Domain\UniqueId;
use Adshares\Common\Domain\Model\Uuid;

final class Campaign
{
    const STATUS_PROCESSING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 2;

    /** @var UniqueId */
    private $id;

    /** @var int */
    private $userId;

    /** @var DemandServer */
    private $demandServer;

    /** @var string */
    private $name;

    /** @var string */
    private $landingUrl;

    /** @var \DateTime */
    private $dateStart;

    /** @var \DateTime */
    private $dateEnd;

    /** @var ArrayCollection */
    private $banners;

    /** @var Budget */
    private $budget;

    private $targetingExcludes = [];

    private $targetingRequires = [];

    /** @var int */
    private $status;


    public function __construct(
        int $userId,
        string $name,
        string $landingUrl,
        \DateTime $dateStart,
        \DateTime $dateEnd,
        array $banners,
        Budget $budget,
        string $demandHost,
        int $status,
        array $targetingRequires = [],
        array $targetingExcludes = []
    )
    {
        $this->id = new Uuid();

        $this->userId = $userId;
        $this->name = $name;
        $this->landingUrl = $landingUrl;

        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;

        $this->banners = new ArrayCollection($banners);

        $this->budget = $budget;
        $this->demandServer = $demandHost;

        $this->targetingRequires = $targetingRequires;
        $this->targetingExcludes = $targetingExcludes;
        $this->status = $status;
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_DELETED;
    }

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
    }

    public static function fromArray(array $data): self
    {
        $budget = new Budget($data['budget'], $data['max_cpc'], $data['max_cpm']);

        $arrayBanners = $data['banners'];
        $banners = [];

        $campaign = new self(
            $data['user_id'],
            $data['name'],
            $data['landing_url'],
            $data['date_start'],
            $data['date_start'],
            $banners,
            $budget,
            $data['demand_host'],
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

    public function getStatus(): int
    {
        return $this->status;
    }
}
