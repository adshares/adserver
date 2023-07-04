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

use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Http\Requests\Filter\FilterType;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Advertiser\Dto\Input\InvalidInputException;
use Adshares\Advertiser\Dto\Input\StatsInput;
use DateTime;
use Illuminate\Http\Request;

class StatsInputTest extends TestCase
{
    public function testConstruct(): void
    {
        $request = new Request(['filter' => ['query' => 'ads']]);
        $filters = FilterCollection::fromRequest($request, [
            'query' => FilterType::String,
        ]);
        $input = new StatsInput(
            ['10000000000000000000000000000000', '20000000000000000000000000000000'],
            new DateTime('@0'),
            new DateTime('@3600'),
            '0123456789ABCDEF0123456789ABCDEF',
            true,
            $filters,
        );

        self::assertEquals(
            ['10000000000000000000000000000000', '20000000000000000000000000000000'],
            $input->getAdvertiserIds(),
        );
        self::assertEquals('10000000000000000000000000000000', $input->getAdvertiserId());
        self::assertEquals(0, $input->getDateStart()->getTimestamp());
        self::assertEquals(3600, $input->getDateEnd()->getTimestamp());
        self::assertEquals('0123456789ABCDEF0123456789ABCDEF', $input->getCampaignId());
        self::assertTrue($input->isShowAdvertisers());
        self::assertEquals('ads', $input->getFilters()->getFilterByName('query')->getValues()[0]);
    }

    public function testConstructFailWhileInvalidDateRange(): void
    {
        self::expectException(InvalidInputException::class);
        self::expectExceptionMessage(
            'Start date (1970-01-01T01:00:00+00:00) must be earlier than end date (1970-01-01T00:00:00+00:00).'
        );

        new StatsInput(
            ['10000000000000000000000000000000', '20000000000000000000000000000000'],
            new DateTime('@3600'),
            new DateTime('@0'),
            '0123456789ABCDEF0123456789ABCDEF',
            true,
        );
    }
}
