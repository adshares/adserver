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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\NetworkMissedCase;
use Adshares\Adserver\Tests\TestCase;

class NetworkMissedCaseTest extends TestCase
{
    public function testCreateWhileSameCaseIdExist(): void
    {
        NetworkMissedCase::factory()->create(['case_id' => '10000000000000000000000000000000']);

        $case = NetworkMissedCase::create(
            '10000000000000000000000000000000',
            '20000000000000000000000000000000',
            '30000000000000000000000000000000',
            '40000000000000000000000000000000',
            '50000000000000000000000000000000',
        );

        self::assertNull($case);
    }
}
