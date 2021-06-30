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

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\InvalidCampaignDateException;
use DateTime;

final class CampaignDate
{
    /** @var DateTime */
    private $dateStart;

    /** @var DateTime|null */
    private $dateEnd;

    /** @var DateTime */
    private $createdAt;

    /** @var DateTime */
    private $updatedAt;

    public function __construct(DateTime $dateStart, ?DateTime $dateEnd, DateTime $createdAt, DateTime $updatedAt)
    {
        if ($dateEnd !== null && $dateEnd <= $dateStart) {
            throw new InvalidCampaignDateException('End date must be greater than start date.');
        }

        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getDateStart(): DateTime
    {
        return $this->dateStart;
    }

    public function getDateEnd(): ?DateTime
    {
        return $this->dateEnd;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
