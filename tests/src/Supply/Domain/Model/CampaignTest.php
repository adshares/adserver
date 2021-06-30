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

declare(strict_types=1);

namespace Adshares\Test\Supply\Domain\Model;

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTime;
use PHPUnit\Framework\TestCase;

final class CampaignTest extends TestCase
{
    public function testCampaignActivate(): void
    {
        $sourceHost = new SourceCampaign(
            'example.com',
            '0001-00000001-0001',
            '0.1',
            new DateTime(),
            new DateTime()
        );

        $campaign = new Campaign(
            Uuid::v4(),
            UUid::v4(),
            'http://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, 100000000000, null),
            $sourceHost,
            Status::toDelete(),
            [],
            []
        );

        $this->assertEquals(Status::STATUS_TO_DELETE, $campaign->getStatus());

        $campaign->activate();

        $this->assertEquals(Status::STATUS_ACTIVE, $campaign->getStatus());
    }

    public function testCampaignDeactivated(): void
    {
        $sourceHost = new SourceCampaign(
            'example.com',
            '0001-00000001-0001',
            '0.1',
            new DateTime(),
            new DateTime()
        );

        $campaign = new Campaign(
            Uuid::v4(),
            Uuid::v4(),
            'http://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, 100000000000, null),
            $sourceHost,
            Status::active(),
            [],
            []
        );

        $this->assertEquals(Status::STATUS_ACTIVE, $campaign->getStatus());

        $campaign->delete();

        $this->assertEquals(Status::STATUS_DELETED, $campaign->getStatus());
    }

    public function testToArray(): void
    {
        $sourceCreatedAt = (new DateTime())->modify('-1 day');
        $sourceUpdatedAt = (new DateTime())->modify('-5 hours');
        $createdAt = (new DateTime())->modify('-2 hours');
        $updatedAt = (new DateTime())->modify('-1 hour');
        $dateStart = (new DateTime())->modify('-1 day');
        $dateEnd = (new DateTime())->modify('+3 days');

        $sourceHost = new SourceCampaign(
            'example.com',
            '0001-00000001-0001',
            '0.1',
            $sourceCreatedAt,
            $sourceUpdatedAt
        );

        $id = Uuid::v4();
        $demandCampaignId = Uuid::v4();

        $campaign = new Campaign(
            $id,
            $demandCampaignId,
            'http://example.com',
            new CampaignDate($dateStart, $dateEnd, $createdAt, $updatedAt),
            [],
            new Budget(1000000000000, 100000000000, null),
            $sourceHost,
            Status::active(),
            [],
            []
        );

        $expected = [
            'id' => $id,
            'demand_campaign_id' => $demandCampaignId,
            'landing_url' => 'http://example.com',
            'max_cpc' => 100000000000,
            'max_cpm' => null,
            'budget' => 1000000000000,
            'source_host' => 'example.com',
            'source_version' => '0.1',
            'source_address' => '0001-00000001-0001',
            'source_created_at' => $sourceCreatedAt,
            'source_updated_at' => $sourceUpdatedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'targeting_requires' => [],
            'targeting_excludes' => [],
            'status' => Status::STATUS_ACTIVE,
        ];

        $this->assertEquals($expected, $campaign->toArray());
        $this->assertEquals([], $campaign->getTargetingRequires());
        $this->assertEquals([], $campaign->getTargetingExcludes());
        $this->assertEquals($dateStart, $campaign->getDateStart());
        $this->assertEquals($dateEnd, $campaign->getDateEnd());
        $this->assertEquals($demandCampaignId, $campaign->getDemandCampaignId());
        $this->assertEquals($id, $campaign->getId());
        $this->assertEquals(Status::STATUS_ACTIVE, $campaign->getStatus());
        $this->assertEquals(new ArrayCollection(), $campaign->getBanners());
    }
}
