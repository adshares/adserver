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

use Adshares\Adserver\Http\Requests\Filter\DateFilter;
use Adshares\Adserver\Tests\TestCase;
use DateTimeImmutable;
use DateTimeInterface;

final class DateFilterTest extends TestCase
{
    public function testDateFilter(): void
    {
        $name = 'test-name';
        $from = '2022-01-01T00:00:00+00:00';
        $to = '2022-02-03T18:45:59+00:00';
        $dateFrom = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $from);
        $dateTo = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $to);

        $filter = new DateFilter($name, $dateFrom);

        self::assertEquals($name, $filter->getName());
        self::assertEquals($dateFrom, $filter->getFrom());
        self::assertNull($filter->getTo());
        self::assertEquals([$dateFrom, null], $filter->getValues());

        $filter->setFrom(null);
        $filter->setTo($dateTo);

        self::assertEquals($name, $filter->getName());
        self::assertNull($filter->getFrom());
        self::assertEquals($dateTo, $filter->getTo());
        self::assertEquals([null, $dateTo], $filter->getValues());
    }
}
