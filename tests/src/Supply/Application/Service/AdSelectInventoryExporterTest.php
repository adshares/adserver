<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Tests\Supply\Application\Service;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use Adshares\Supply\Application\Service\Exception\NoBannersForGivenCampaign;
use Adshares\Supply\Application\Service\InventoryExporter;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use DateTime;
use PHPUnit\Framework\TestCase;

class AdSelectInventoryExporterTest extends TestCase
{
    public function testWhenNoBannersForGivenCampaign(): void
    {
        $this->expectException(NoBannersForGivenCampaign::class);

        $campaignId = Uuid::v4();
        $campaign = new Campaign(
            $campaignId,
            UUid::fromString('4a27f6a938254573abe47810a0b03748'),
            Uuid::v4(),
            'http://example.com',
            new CampaignDate(new DateTime(), new DateTime(), new DateTime(), new DateTime()),
            [],
            new Budget(10, null, 2),
            new SourceCampaign('localhost', '0000-00000000-0001', '0.1', new DateTime(), new DateTime()),
            Campaign::STATUS_PROCESSING,
            [],
            []
        );

        $client = $this->createMock(InventoryExporter::class);

        $service = new AdSelectInventoryExporter($client);
        $service->export($campaign);
    }
}
