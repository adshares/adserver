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

namespace Adshares\Tests\Supply\Domain\Model;

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Model\Banner;
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
        $banner = $this->createMock(Banner::class);
        $banner->expects(self::once())->method('activate');

        $campaign = new Campaign(
            Uuid::v4(),
            Uuid::v4(),
            'https://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [$banner],
            new Budget(1000000000000, 100000000000, null),
            self::sourceCampaign(),
            Status::toDelete(),
            'web',
            null
        );

        $this->assertEquals(Status::STATUS_TO_DELETE, $campaign->getStatus());

        $campaign->activate();

        $this->assertEquals(Status::STATUS_ACTIVE, $campaign->getStatus());
    }

    public function testCampaignDeactivated(): void
    {
        $banner = $this->createMock(Banner::class);
        $banner->expects(self::once())->method('delete');

        $campaign = new Campaign(
            Uuid::v4(),
            Uuid::v4(),
            'https://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, 100000000000, null),
            self::sourceCampaign(),
            Status::active(),
            'web',
            null
        );
        $campaign->setBanners(new ArrayCollection([$banner]));

        $this->assertEquals(Status::STATUS_ACTIVE, $campaign->getStatus());

        $campaign->delete();

        $this->assertEquals(Status::STATUS_DELETED, $campaign->getStatus());
    }

    public function testToArray(): void
    {
        $sourceAddress = '0001-00000001-8B4E';
        $sourceCreatedAt = (new DateTime())->modify('-1 day');
        $sourceUpdatedAt = (new DateTime())->modify('-5 hours');
        $createdAt = (new DateTime())->modify('-2 hours');
        $updatedAt = (new DateTime())->modify('-1 hour');
        $dateStart = (new DateTime())->modify('-1 day');
        $dateEnd = (new DateTime())->modify('+3 days');

        $sourceHost = new SourceCampaign(
            'example.com',
            $sourceAddress,
            '0.1',
            $sourceCreatedAt,
            $sourceUpdatedAt
        );

        $id = Uuid::v4();
        $demandCampaignId = Uuid::v4();
        $budget = 1000000000000;
        $maxCpc = 100000000000;
        $medium = 'web';

        $campaign = new Campaign(
            $id,
            $demandCampaignId,
            'https://example.com',
            new CampaignDate($dateStart, $dateEnd, $createdAt, $updatedAt),
            [],
            new Budget($budget, $maxCpc, null),
            $sourceHost,
            Status::active(),
            $medium,
            null
        );

        $expected = [
            'id' => $id,
            'demand_campaign_id' => $demandCampaignId,
            'landing_url' => 'https://example.com',
            'max_cpc' => $maxCpc,
            'max_cpm' => null,
            'budget' => $budget,
            'source_host' => 'example.com',
            'source_version' => '0.1',
            'source_address' => $sourceAddress,
            'source_created_at' => $sourceCreatedAt,
            'source_updated_at' => $sourceUpdatedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'targeting_requires' => [],
            'targeting_excludes' => [],
            'status' => Status::STATUS_ACTIVE,
            'medium' => $medium,
            'vendor' => null,
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
        $this->assertEquals($sourceAddress, $campaign->getSourceAddress());
        $this->assertEquals($budget, $campaign->getBudget());
        $this->assertEquals($maxCpc, $campaign->getMaxCpc());
        $this->assertNull($campaign->getMaxCpm());
        $this->assertEquals($medium, $campaign->getMedium());
        $this->assertNull($campaign->getVendor());
    }

    private static function sourceCampaign(): SourceCampaign
    {
        return new SourceCampaign(
            'example.com',
            '0001-00000001-8B4E',
            '0.1',
            new DateTime(),
            new DateTime()
        );
    }
}
