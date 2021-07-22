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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Tests\TestCase;

class RefLinkTest extends TestCase
{
    public function testFetchByToken(): void
    {
        $refLink1 = factory(RefLink::class)->create(['token' => 'foo1']);
        factory(RefLink::class)->create(['token' => 'foo2']);

        $dbRefLink = RefLink::fetchByToken('foo1');
        $this->assertNotNull($dbRefLink);
        $this->assertEquals($refLink1->id, $dbRefLink->id);

        $dbRefLink = RefLink::fetchByToken('dummy_token');
        $this->assertNull($dbRefLink);
    }

    public function testFetchOutdatedRefLink(): void
    {
        $refLink1 = factory(RefLink::class)->create(['token' => 'foo1', 'valid_until' => now()->addDay()]);
        factory(RefLink::class)->create(['token' => 'foo2', 'valid_until' => now()->subDay()]);

        $dbRefLink = RefLink::fetchByToken('foo1');
        $this->assertNotNull($dbRefLink);
        $this->assertEquals($refLink1->id, $dbRefLink->id);

        $dbRefLink = RefLink::fetchByToken('foo2');
        $this->assertNull($dbRefLink);
    }

    public function testFetchUsedRefLink(): void
    {
        $refLink1 = factory(RefLink::class)->create(['token' => 'foo1', 'single_use' => true, 'used' => false]);
        $refLink2 = factory(RefLink::class)->create(['token' => 'foo2', 'single_use' => false, 'used' => true]);
        factory(RefLink::class)->create(['token' => 'foo3', 'single_use' => true, 'used' => true]);

        $dbRefLink = RefLink::fetchByToken('foo1');
        $this->assertNotNull($dbRefLink);
        $this->assertEquals($refLink1->id, $dbRefLink->id);

        $dbRefLink = RefLink::fetchByToken('foo2');
        $this->assertNotNull($dbRefLink);
        $this->assertEquals($refLink2->id, $dbRefLink->id);

        $dbRefLink = RefLink::fetchByToken('foo3');
        $this->assertNull($dbRefLink);
    }
}
