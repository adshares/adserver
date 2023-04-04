<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Advertiser\Dto\Input;

use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use DateTime;
use DateTimeInterface;

class StatsInput
{
    private ?array $advertiserIds;

    public function __construct(
        ?array $advertiserIds,
        private readonly DateTime $dateStart,
        private readonly DateTime $dateEnd,
        private readonly ?string $campaignId = null,
        private readonly bool $showAdvertisers = false,
        private readonly ?FilterCollection $filters = null,
    ) {
        if ($dateEnd < $dateStart) {
            throw new InvalidInputException(sprintf(
                'Start date (%s) must be earlier than end date (%s).',
                $dateStart->format(DateTimeInterface::ATOM),
                $dateEnd->format(DateTimeInterface::ATOM)
            ));
        }

        $this->advertiserIds = $advertiserIds;
    }

    public function getAdvertiserIds(): ?array
    {
        return $this->advertiserIds;
    }

    public function getAdvertiserId(): ?string
    {
        return null !== $this->advertiserIds ? reset($this->advertiserIds) : null;
    }

    public function isShowAdvertisers(): bool
    {
        return $this->showAdvertisers;
    }

    public function getDateStart(): DateTime
    {
        return $this->dateStart;
    }

    public function getDateEnd(): DateTime
    {
        return $this->dateEnd;
    }

    public function getCampaignId(): ?string
    {
        return $this->campaignId;
    }

    public function getFilters(): ?FilterCollection
    {
        return $this->filters;
    }
}
