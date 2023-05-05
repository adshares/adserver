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

namespace Adshares\Tests\Advertiser\Dto\Input;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Advertiser\Dto\Input\ChartInput;
use Adshares\Advertiser\Dto\Input\InvalidInputException;
use DateTime;

class ChartInputTest extends TestCase
{
    public function testConstructWithInvalidResolution(): void
    {
        self::expectException(InvalidInputException::class);
        self::expectExceptionMessage('Unsupported chart resolution `two hours`.');

        new ChartInput(
            '10000000000000000000000000000000',
            'view',
            'two hours',
            new DateTime(),
            new DateTime('+1 day'),
        );
    }
}
