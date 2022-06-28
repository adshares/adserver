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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Tests\TestCase;
use DateTime;

class ServeDomainTest extends TestCase
{
    public function testUpsert(): void
    {
        self::assertEquals(0, ServeDomain::count());

        ServeDomain::upsert('https://example.com');
        self::assertEquals(1, ServeDomain::count());
    }

    public function testChangeUrlHost(): void
    {
        ServeDomain::factory()->create(['base_url' => 'https://example.com']);

        self::assertEquals(
            'https://example.com/serve/x0123456789ABCDEF.doc?v=1234',
            ServeDomain::changeUrlHost('https://adshares.net/serve/x0123456789ABCDEF.doc?v=1234')
        );
    }

    public function testCurrent(): void
    {
        ServeDomain::factory()->create(['base_url' => 'https://example.com']);

        self::assertEquals('https://app.example.com', ServeDomain::current('app'));
    }

    public function testClear(): void
    {
        ServeDomain::factory()->create(
            ['base_url' => 'https://example.com', 'updated_at' => new DateTime('-1 year')]
        );
        self::assertEquals(1, ServeDomain::count());

        ServeDomain::clear();
        self::assertEquals(0, ServeDomain::count());
    }

    public function testFetch(): void
    {
        ServeDomain::factory()->create(['base_url' => 'https://example.com']);

        self::assertEquals(['https://example.com'], ServeDomain::fetch());
    }
}
