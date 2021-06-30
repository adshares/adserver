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

namespace Adshares\Test\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\Exception\InvalidCampaignDateException;
use PHPUnit\Framework\TestCase;
use DateTime;

final class CampaignDateTest extends TestCase
{
    public function testWhenDateStartIsGreaterThanDateEnd(): void
    {
        $this->expectException(InvalidCampaignDateException::class);
        $this->expectExceptionMessage('End date must be greater than start date.');

        $dateStart = new DateTime();
        $dateEnd = (clone $dateStart)->modify('-1 hour');

        new CampaignDate($dateStart, $dateEnd, new DateTime(), new DateTime());
    }

    public function testWhenDateStartIsSmallerThanDateEnd(): void
    {
        $dateStart = new DateTime();
        $dateEnd = (new DateTime())->modify('+1 hour');
        $createdAt = new DateTime();
        $updatedAt = new DateTime();

        $campaignDate = new CampaignDate($dateStart, $dateEnd, $createdAt, $updatedAt);

        $this->assertEquals($dateStart, $campaignDate->getDateStart());
        $this->assertEquals($dateEnd, $campaignDate->getDateEnd());
        $this->assertEquals($createdAt, $campaignDate->getCreatedAt());
        $this->assertEquals($updatedAt, $campaignDate->getUpdatedAt());
    }
}
