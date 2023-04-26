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

namespace Adshares\Adserver\Tests\Client\Mapper\AdPay;

use Adshares\Adserver\Client\Mapper\AdPay\DemandEventMapper;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Tests\TestCase;

final class DemandEventMapperTest extends TestCase
{
    public function testMapConversionCollectionToArray(): void
    {
        Conversion::factory()->create();
        $conversions = Conversion::all();

        $mapped = DemandEventMapper::mapConversionCollectionToArray($conversions);

        self::assertCount(1, $mapped);
    }

    public function testMapConversionCollectionToArrayWhileEventDeleted(): void
    {
        Conversion::factory()->create();
        Conversion::first()->event->delete();
        $conversions = Conversion::all();

        $mapped = DemandEventMapper::mapConversionCollectionToArray($conversions);

        self::assertEmpty($mapped);
    }
}
