<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Test\Supply\Domain\Model;

use Adshares\Supply\Domain\Model\Budget;
use Adshares\Supply\Domain\Model\Exception\InvalidBudgetValueException;
use PHPUnit\Framework\TestCase;

class BudgetTest extends TestCase
{
    public function testWhenBudgetValueIsNegative()
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(-10, 1, 1);
    }

    public function testWhenMaxCpcValueIsNegative()
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(1, 0, 1);
    }

    public function testWhenMaxCpmValueIsNegative()
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(10, 1, -1);
    }

    public function testWhenTotalIsSmallerThanBudget()
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(10, 6, 6);
    }

    public function testWhenMaxCpcIsSmallerThanBudget()
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(10, 11, null);
    }

    public function testWhenMaxCpmIsSmallerThanBudget()
    {
        $this->expectException(InvalidBudgetValueException::class);

        new Budget(10, null, 12);
    }
}
