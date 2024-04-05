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

declare(strict_types=1);

namespace Adshares\Tests\Publisher\Dto\Result\Stats;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Publisher\Dto\Result\Stats\Calculation;

class CalculationTest extends TestCase
{
    public function testToArray(): void
    {
        $calculation = new Calculation(1, 2, 3.14, 4, 5, 6);

        self::assertEquals(
            [
                'clicks' => 1,
                'impressions' => 2,
                'ctr' => 3.14,
                'averageRpc' => 4,
                'averageRpm' => 5,
                'revenue' => 6,
            ],
            $calculation->toArray(),
        );
    }
}
