<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Tests\Supply\Domain\Model;

use Adshares\Supply\Domain\Model\Budget;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\DemandServer;
use PHPUnit\Framework\TestCase;

class CampaignTest extends TestCase
{
    public function testCampaignActivate()
    {
        $campaign = new Campaign(
            1,
            'campaign name',
            'http://example.com',
            new \DateTime(),
            new \DateTime(),
            [],
            new Budget(10, 1, null),
            new DemandServer(),
            Campaign::STATUS_DELETED,
            [],
            []
        );

        $this->assertEquals(Campaign::STATUS_DELETED, $campaign->getStatus());

        $campaign->activate();

        $this->assertEquals(Campaign::STATUS_ACTIVE, $campaign->getStatus());
    }

    public function testCampaignDeactivated()
    {
        $campaign = new Campaign(
            1,
            'campaign name',
            'http://example.com',
            new \DateTime(),
            new \DateTime(),
            [],
            new Budget(10, 1, 1),
            new DemandServer(),
            Campaign::STATUS_ACTIVE,
            [],
            []
        );

        $this->assertEquals(Campaign::STATUS_ACTIVE, $campaign->getStatus());

        $campaign->deactivate();

        $this->assertEquals(Campaign::STATUS_DELETED, $campaign->getStatus());
    }
}
