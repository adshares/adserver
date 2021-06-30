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

namespace Adshares\Publisher\Dto\Input;

use DateTime;
use DateTimeInterface;

class StatsInput
{
    /** @var string|null */
    private $publisherId;

    /** @var DateTime */
    private $dateStart;

    /** @var DateTime */
    private $dateEnd;

    /** @var string|null */
    private $siteId;

    public function __construct(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ) {
        if ($dateEnd < $dateStart) {
            throw new InvalidInputException(sprintf(
                'Start date (%s) must be earlier than end date (%s).',
                $dateStart->format(DateTimeInterface::ATOM),
                $dateEnd->format(DateTimeInterface::ATOM)
            ));
        }

        $this->publisherId = $publisherId;
        $this->siteId = $siteId;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function getPublisherId(): ?string
    {
        return $this->publisherId;
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
