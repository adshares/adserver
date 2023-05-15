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
use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
use DateTimeInterface;

use function in_array;

final class ChartInput
{
    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_VIEW_INVALID_RATE,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_CLICK_INVALID_RATE,
        StatsRepository::TYPE_CPC,
        StatsRepository::TYPE_CPM,
        StatsRepository::TYPE_SUM,
        StatsRepository::TYPE_SUM_BY_PAYMENT,
        StatsRepository::TYPE_CTR,
    ];

    private ChartResolution $resolution;

    public function __construct(
        private readonly string $advertiserId,
        private readonly string $type,
        string $resolution,
        private readonly DateTime $dateStart,
        private readonly DateTime $dateEnd,
        private readonly ?string $campaignId = null,
        private readonly ?FilterCollection $filters = null,
    ) {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidInputException(sprintf('Unsupported chart type `%s`.', $type));
        }

        $chartResolution = ChartResolution::tryFrom($resolution);
        if (null === $chartResolution) {
            throw new InvalidInputException(sprintf('Unsupported chart resolution `%s`.', $resolution));
        }

        if ($dateEnd < $dateStart) {
            throw new InvalidInputException(sprintf(
                'Start date (%s) must be earlier than end date (%s).',
                $dateStart->format(DateTimeInterface::ATOM),
                $dateEnd->format(DateTimeInterface::ATOM)
            ));
        }

        $this->resolution = $chartResolution;
    }

    public function getAdvertiserId(): string
    {
        return $this->advertiserId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResolution(): ChartResolution
    {
        return $this->resolution;
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
