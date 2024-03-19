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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\JoiningFeeLog;
use Adshares\Adserver\Tests\TestCase;
use DateTimeImmutable;

class JoiningFeeLogTest extends TestCase
{
    public function testFetchUnpaid(): void
    {
        $from = new DateTimeImmutable('2024-01-01 00:00:00');
        $to = new DateTimeImmutable('2024-01-01 23:59:59');
        JoiningFeeLog::factory()
            ->count(2)
            ->sequence(
                [
                    'computed_at' => new DateTimeImmutable('2024-01-01 12:00:00'),
                ],
                [
                    'computed_at' => new DateTimeImmutable('2024-01-02 12:00:00'),
                ],
            )
            ->create();

        $logs = JoiningFeeLog::fetchUnpaid($from, $to);

        self::assertCount(1, $logs);

        $logs = JoiningFeeLog::fetchUnpaid($from, null);

        self::assertCount(2, $logs);
    }
}
