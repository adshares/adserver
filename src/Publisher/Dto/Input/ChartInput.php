<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Publisher\Dto\Input;

use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use function in_array;

final class ChartInput
{
    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_VIEW_INVALID_RATE,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_CLICK_INVALID_RATE,
        StatsRepository::TYPE_RPC,
        StatsRepository::TYPE_RPM,
        StatsRepository::TYPE_SUM,
        StatsRepository::TYPE_CTR,
    ];

    private const ALLOWED_RESOLUTIONS = [
        StatsRepository::RESOLUTION_HOUR,
        StatsRepository::RESOLUTION_DAY,
        StatsRepository::RESOLUTION_WEEK,
        StatsRepository::RESOLUTION_MONTH,
        StatsRepository::RESOLUTION_QUARTER,
        StatsRepository::RESOLUTION_YEAR,
    ];

    /** @var string */
    private $publisherId;

    /** @var string  */
    private $type;

    /** @var string  */
    private $resolution;

    /** @var DateTime */
    private $dateStart;

    /** @var DateTime */
    private $dateEnd;

    /** @var string|null */
    private $siteId;

    public function __construct(
        string $publisherId,
        string $type,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ) {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidInputException(sprintf('Unsupported chart type `%s`.', $type));
        }

        if (!in_array($resolution, self::ALLOWED_RESOLUTIONS, true)) {
            throw new InvalidInputException(sprintf('Unsupported chart resolution `%s`.', $resolution));
        }

        if ($dateEnd < $dateStart) {
            throw new InvalidInputException(sprintf(
                'Start date (%s) must be earlier than end date (%s).',
                $dateStart->format(DateTime::ATOM),
                $dateEnd->format(DateTime::ATOM)
            ));
        }

        $this->type = $type;
        $this->resolution = $resolution;
        $this->publisherId = $publisherId;
        $this->siteId = $siteId;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function getPublisherId(): string
    {
        return $this->publisherId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResolution(): string
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

    public function getSiteId(): ?string
    {
        return $this->siteId;
    }
}
