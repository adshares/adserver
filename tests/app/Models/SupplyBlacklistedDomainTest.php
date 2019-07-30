<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\SupplyBlacklistedDomain;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplyBlacklistedDomainTest extends TestCase
{
    use RefreshDatabase;

    public function testBlacklisted(): void
    {
        SupplyBlacklistedDomain::register('example.com');

        $this->assertFalse(SupplyBlacklistedDomain::isDomainBlacklisted('com'));
        $this->assertTrue(SupplyBlacklistedDomain::isDomainBlacklisted('example.com'));
        $this->assertTrue(SupplyBlacklistedDomain::isDomainBlacklisted('one.example.com'));
        $this->assertTrue(SupplyBlacklistedDomain::isDomainBlacklisted('www.one.example.com'));
    }
}
