<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\JoiningFee;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use DateTimeImmutable;

class JoiningFeeTest extends TestCase
{
    public function testGetAllocationAmount(): void
    {
        Config::updateAdminSettings([Config::JOINING_FEE_ALLOCATION_PERIOD_IN_HOURS => 3]);
        DatabaseConfigReader::overwriteAdministrationConfig();

        $joiningFee = JoiningFee::factory()->create();
        $date = new DateTimeImmutable();

        $amount = [];
        for ($i = 0; $i < 10; $i++) {
            $date = $date->modify('+1 hour');
            $amount[] = $joiningFee->getAllocationAmount($date);
        }
        self::assertEquals(
            [
                1_666_666_666_666,//50/3
                1_666_666_666_666,
                1_666_666_666_666,
                833_333_333_333,//25/3
                833_333_333_333,
                833_333_333_333,
                416_666_666_666,//12.5/3
                416_666_666_666,
                416_666_666_666,
                208_333_333_333,//6.25/3
            ],
            $amount
        );
    }
}
