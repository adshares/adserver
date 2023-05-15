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

declare(strict_types=1);

namespace Adshares\Tests\Publisher\Dto\Input;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use Adshares\Publisher\Dto\Input\ChartInput;
use Adshares\Publisher\Dto\Input\InvalidInputException;
use DateTime;

class ChartInputTest extends TestCase
{
    public function testConstruct(): void
    {
        $chartInput = new ChartInput(
            '10000000000000000000000000000000',
            'view',
            'hour',
            new DateTime('@0'),
            new DateTime('@3600'),
        );

        self::assertEquals('10000000000000000000000000000000', $chartInput->getPublisherId());
        self::assertEquals('view', $chartInput->getType());
        self::assertEquals(ChartResolution::HOUR, $chartInput->getResolution());
        self::assertEquals(0, $chartInput->getDateStart()->getTimestamp());
        self::assertEquals(3600, $chartInput->getDateEnd()->getTimestamp());
        self::assertNull($chartInput->getSiteId());
    }

    public function testConstructWithInvalidType(): void
    {
        self::expectException(InvalidInputException::class);
        self::expectExceptionMessage('Unsupported chart type `views`.');

        new ChartInput(
            '10000000000000000000000000000000',
            'views',
            'hour',
            new DateTime(),
            new DateTime('+1 day'),
        );
    }

    public function testConstructWithInvalidResolution(): void
    {
        self::expectException(InvalidInputException::class);
        self::expectExceptionMessage('Unsupported chart resolution `two hours`.');

        new ChartInput(
            '',
            'view',
            'two hours',
            new DateTime(),
            new DateTime('+1 day'),
        );
    }

    public function testConstructWithInvalidDateRange(): void
    {
        self::expectException(InvalidInputException::class);
        self::expectExceptionMessage(
            'Start date (1970-01-01T00:01:00+00:00) must be earlier than end date (1970-01-01T00:00:00+00:00).',
        );

        new ChartInput(
            '10000000000000000000000000000000',
            'view',
            'hour',
            new DateTime('@60'),
            new DateTime('@0'),
        );
    }
}
