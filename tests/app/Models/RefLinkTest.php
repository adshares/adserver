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

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RefLinkTest extends TestCase
{
    public function testStatus(): void
    {
        $refLink = new RefLink();
        $refLink->valid_until = null;
        $refLink->single_use = false;
        $refLink->used = false;
        $this->assertEquals(RefLink::STATUS_ACTIVE, $refLink->status);

        $refLink = new RefLink();
        $refLink->valid_until = null;
        $refLink->single_use = true;
        $refLink->used = false;
        $this->assertEquals(RefLink::STATUS_ACTIVE, $refLink->status);

        $refLink = new RefLink();
        $refLink->valid_until = null;
        $refLink->single_use = true;
        $refLink->used = true;
        $this->assertEquals(RefLink::STATUS_USED, $refLink->status);

        $refLink = new RefLink();
        $refLink->valid_until = now()->addDay();
        $refLink->single_use = false;
        $refLink->used = false;
        $this->assertEquals(RefLink::STATUS_ACTIVE, $refLink->status);

        $refLink = new RefLink();
        $refLink->valid_until = now()->subDay();
        $refLink->single_use = false;
        $refLink->used = false;
        $this->assertEquals(RefLink::STATUS_OUTDATED, $refLink->status);

        $refLink = new RefLink();
        $refLink->valid_until = now()->subDay();
        $refLink->single_use = true;
        $refLink->used = true;
        $this->assertEquals(RefLink::STATUS_OUTDATED, $refLink->status);
    }

    public function testRefundActive(): void
    {
        $refLink = new RefLink();
        $refLink->refund_valid_until = null;
        $this->assertTrue($refLink->refund_active);

        $refLink = new RefLink();
        $refLink->refund_valid_until = now()->addDay();
        $this->assertTrue($refLink->refund_active);

        $refLink = new RefLink();
        $refLink->refund_valid_until = now()->subDay();
        $this->assertFalse($refLink->refund_active);
    }

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

    public function testCalculateRefundAmount(): void
    {
        Config::updateAdminSettings([Config::REFERRAL_REFUND_COMMISSION => 0.1]);

        /** @var RefLink $refLink */
        $refLink = factory(RefLink::class)->create();
        $this->assertEquals(0, $refLink->calculateRefund(0));
        $this->assertEquals(10, $refLink->calculateRefund(100));
        $this->assertEquals(0, $refLink->calculateRefund(1));

        $refLink = factory(RefLink::class)->create(['kept_refund' => 0.33333]);
        $this->assertEquals(3, $refLink->calculateRefund(100));

        $refLink = factory(RefLink::class)->create(['kept_refund' => 0.66666]);
        $this->assertEquals(7, $refLink->calculateRefund(100));

        $refLink = factory(RefLink::class)->create(['kept_refund' => 0]);
        $this->assertEquals(0, $refLink->calculateRefund(100));

        $refLink = factory(RefLink::class)->create(['refund' => 0.6, 'kept_refund' => 0.8]);
        $this->assertEquals(0, $refLink->calculateRefund(0));
        $this->assertEquals(48, $refLink->calculateRefund(100));

        $refLink = factory(RefLink::class)->create(['refund' => 0.5, 'kept_refund' => 0.5]);
        $this->assertEquals(1, $refLink->calculateRefund(7));
    }

    public function testCalculateBonusAmount(): void
    {
        Config::updateAdminSettings([Config::REFERRAL_REFUND_COMMISSION => 0.1]);

        /** @var RefLink $refLink */
        $refLink = factory(RefLink::class)->create();
        $this->assertEquals(0, $refLink->calculateBonus(0));
        $this->assertEquals(0, $refLink->calculateBonus(100));
        $this->assertEquals(0, $refLink->calculateBonus(10));

        $refLink = factory(RefLink::class)->create(['kept_refund' => 0.33333]);
        $this->assertEquals(7, $refLink->calculateBonus(100));

        $refLink = factory(RefLink::class)->create(['kept_refund' => 0.66666]);
        $this->assertEquals(3, $refLink->calculateBonus(100));

        $refLink = factory(RefLink::class)->create(['kept_refund' => 0]);
        $this->assertEquals(10, $refLink->calculateBonus(100));

        $refLink = factory(RefLink::class)->create(['refund' => 0.6, 'kept_refund' => 0.8]);
        $this->assertEquals(0, $refLink->calculateBonus(0));
        $this->assertEquals(12, $refLink->calculateBonus(100));

        $refLink = factory(RefLink::class)->create(['refund' => 0.5, 'kept_refund' => 0.5]);
        $this->assertEquals(2, $refLink->calculateBonus(7));
    }
}
