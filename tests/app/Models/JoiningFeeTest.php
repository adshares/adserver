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

use Adshares\Adserver\Models\JoiningFee;
use Adshares\Adserver\Tests\TestCase;

class JoiningFeeTest extends TestCase
{
    public function testGetAllocationAmount(): void
    {
        $joiningFee = JoiningFee::factory()->create();
        $totalAmount = 0;
        $hours = 30 * 24 + 1;// more than 1 period
        for ($i = 0; $i < $hours; $i++) {
            $amount = $joiningFee->getAllocationAmount();
            $joiningFee->left_amount -= $amount;
            $totalAmount += $amount;
        }

        self::assertGreaterThan($joiningFee->total_amount / 2, $totalAmount);
    }
}
