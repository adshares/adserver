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

namespace Adshares\Tests\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

final class CommissionTest extends TestCase
{
    /** @dataProvider commissionProvider */
    public function testCommission(float $value, bool $expectedException = false): void
    {
        if ($expectedException) {
            $this->expectException(RuntimeException::class);
        }

        $commission = new Commission($value);

        $this->assertEquals($value, $commission->getValue());
    }

    public function testRoundUp(): void
    {
        $value = 0.56789;
        $commission = new Commission($value);

        $this->assertEquals(0.5679, $commission->getValue());
    }

    public function commissionProvider(): array
    {
        return [
            [0.245],
            [0],
            [0.100],
            [0.877],
            [100.01, true],
            [-1, true],

        ];
    }
}
