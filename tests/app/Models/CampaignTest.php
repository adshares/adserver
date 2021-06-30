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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Tests\TestCase;
use DateTime;
use DateTimeInterface;

class CampaignTest extends TestCase
{
    /**
     * @dataProvider campaignTimeEndProvider
     *
     * @param bool $expectedResult
     * @param string|null $timeEnd
     */
    public function testCampaignOutdated(bool $expectedResult, ?string $timeEnd): void
    {
        $campaign = new Campaign();
        $campaign->time_end = $timeEnd;

        self::assertEquals($expectedResult, $campaign->isOutdated());
    }

    public function campaignTimeEndProvider(): array
    {
        return [
            [false, null],
            [false, (new DateTime('+1 hour'))->format(DateTimeInterface::ATOM)],
            [true, (new DateTime())->format(DateTimeInterface::ATOM)],
            [true, (new DateTime('-1 hour'))->format(DateTimeInterface::ATOM)],
        ];
    }
}
