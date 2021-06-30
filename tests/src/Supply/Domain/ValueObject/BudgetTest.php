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

namespace Adshares\Test\Supply\ValueObject\Model;

use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\Exception\InvalidBudgetValueException;
use PHPUnit\Framework\TestCase;

final class BudgetTest extends TestCase
{
    public function testWhenBudgetValueIsNegative(): void
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(-1000000000000, 100000000000, 100000000000);
    }

    public function testWhenMaxCpcValueIsSmallerThan0(): void
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(100000000000, -100000000000, 100000000000);
    }

    public function testWhenMaxCpmValueIsNegative(): void
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(1000000000000, 100000000000, -100000000000);
    }

    public function testWhenInputDataAreCorrect(): void
    {
        $budget = new Budget(1000000000000, 100000000000, 200000000000);

        $this->assertEquals(1000000000000, $budget->getBudget());
        $this->assertEquals(100000000000, $budget->getMaxCpc());
        $this->assertEquals(200000000000, $budget->getMaxCpm());
    }
}
