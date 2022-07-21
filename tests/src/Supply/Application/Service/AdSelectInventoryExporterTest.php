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

namespace Adshares\Tests\Supply\Application\Service;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\CampaignDate;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AdSelectInventoryExporterTest extends TestCase
{
    public function testWhenNoBannersForGivenCampaign(): void
    {
        $campaignToDelete = self::campaign();
        $campaignId = $campaignToDelete->getId();

        $client = $this->createMock(AdSelect::class);
        $client
            ->expects(self::once())
            ->method('exportInventory');

        $repository = $this->createMock(CampaignRepository::class);
        $repository
            ->expects(self::once())
            ->method('deleteCampaign')
            ->willReturnCallback(function ($campaign) use ($campaignId) {
                /** @var Campaign $campaign */
                self::assertEquals($campaignId, $campaign->getId());
            });

        $service = new AdSelectInventoryExporter($client, $repository, new NullLogger());
        $service->export(new CampaignCollection(self::campaign()), new CampaignCollection($campaignToDelete));
    }

    public function testHandlingAdSelectError500WhileUpdating(): void
    {
        $client = self::createMock(AdSelect::class);
        $client
            ->expects(self::once())
            ->method('exportInventory')
            ->willThrowException(new UnexpectedClientResponseException('test', 500));
        $client
            ->expects(self::never())
            ->method('deleteFromInventory');

        $service = new AdSelectInventoryExporter(
            $client,
            self::createMock(CampaignRepository::class),
            new NullLogger()
        );
        $service->export(new CampaignCollection(self::campaign()), new CampaignCollection(self::campaign()));
    }

    public function testHandlingAdSelectError400WhileUpdating(): void
    {
        $client = self::createMock(AdSelect::class);
        $client
            ->expects(self::once())
            ->method('exportInventory')
            ->willThrowException(new UnexpectedClientResponseException('test', 400));
        $client
            ->expects(self::once())
            ->method('deleteFromInventory');

        $service = new AdSelectInventoryExporter(
            $client,
            self::createMock(CampaignRepository::class),
            new NullLogger()
        );
        $service->export(new CampaignCollection(self::campaign()), new CampaignCollection(self::campaign()));
    }

    public function testHandlingAdSelectError500WhileDeleting(): void
    {
        $client = self::createMock(AdSelect::class);
        $client
            ->expects(self::once())
            ->method('exportInventory');
        $client
            ->expects(self::once())
            ->method('deleteFromInventory')
            ->willThrowException(new UnexpectedClientResponseException('test', 500));

        $service = new AdSelectInventoryExporter(
            $client,
            self::createMock(CampaignRepository::class),
            new NullLogger()
        );
        $service->export(new CampaignCollection(self::campaign()), new CampaignCollection(self::campaign()));
    }

    public function testHandlingAdSelectError400WhileDeleting(): void
    {
        $client = self::createMock(AdSelect::class);
        $client
            ->expects(self::once())
            ->method('exportInventory');
        $client
            ->expects(self::once())
            ->method('deleteFromInventory')
            ->willThrowException(new UnexpectedClientResponseException('test', 400));

        $service = new AdSelectInventoryExporter(
            $client,
            self::createMock(CampaignRepository::class),
            new NullLogger()
        );
        $service->export(new CampaignCollection(self::campaign()), new CampaignCollection(self::campaign()));
    }

    private static function campaign(): Campaign
    {
        return new Campaign(
            Uuid::v4(),
            Uuid::v4(),
            'https://example.com',
            new CampaignDate(new DateTime(), (new DateTime())->modify('+1 hour'), new DateTime(), new DateTime()),
            [],
            new Budget(1000000000000, null, 200000000000),
            new SourceCampaign('localhost', '0000-00000000-0001', '0.1', new DateTime(), new DateTime()),
            Status::processing(),
            'web',
            null
        );
    }
}
