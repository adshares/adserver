<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\Bucketer;
use PHPUnit\Framework\TestCase;

final class BucketerTest extends TestCase
{
    public function testBucketerSimple()
    {
        $stats = new Bucketer(8);

        $items = [20, 40, 24, 30, 10, 38, 7, 19];

        foreach ($items as $value) {
            $stats->add($value);
        }

        $this->assertSame(10, $stats->percentile(0.25));
        $this->assertSame(20, $stats->percentile(0.5));
        $this->assertSame(30, $stats->percentile(0.75));
        $this->assertSame(40, $stats->percentile(1));
    }
}
