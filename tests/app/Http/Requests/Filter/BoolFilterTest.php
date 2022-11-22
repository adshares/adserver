<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Requests\Filter;

use Adshares\Adserver\Http\Requests\Filter\BoolFilter;
use Adshares\Adserver\Tests\TestCase;

final class BoolFilterTest extends TestCase
{
    public function testBoolFilter(): void
    {
        $name = 'test-name';

        $filter = new BoolFilter($name, true);

        self::assertEquals($name, $filter->getName());
        self::assertTrue($filter->isChecked());
        self::assertEquals([true], $filter->getValues());

        $filter->setChecked(false);

        self::assertEquals($name, $filter->getName());
        self::assertFalse($filter->isChecked());
        self::assertEquals([false], $filter->getValues());
    }
}
