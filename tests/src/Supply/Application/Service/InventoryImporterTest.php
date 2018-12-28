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

namespace Adshares\Test\Supply\Application\Service;

use Adshares\Adserver\Client\DummyDemandClient;
use Adshares\Common\Application\TransactionManager;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\InventoryImporter;
use Adshares\Supply\Application\Service\MarkedCampaignsAsDeleted;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Repository\Exception\CampaignRepositoryException;
use Adshares\Supply\Domain\ValueObject\Status;
use PHPUnit\Framework\TestCase;

final class InventoryImporterTest extends TestCase
{
    public function testImportWhenDemandClientReturnsNoCampaigns(): void
    {
        $repository = $this->repositoryMock();
        $demandClient = $this->clientMock();
        $transactionManager = $this->transactionManagerMock();
        $markCampaignAsDeletedService = new MarkedCampaignsAsDeleted($repository);

        $transactionManager
            ->expects($this->never())
            ->method('begin');

        $inventoryImporter = new InventoryImporter(
            $markCampaignAsDeletedService,
            $repository,
            $demandClient,
            $transactionManager
        );

        $inventoryImporter->import('localhost:8101');

        $this->doesNotPerformAssertions();
    }

    public function testImportWhenDemandClientReturnsUnexpectedResponse(): void
    {
        $repository = $this->repositoryMock();
        $demandClient = $this->clientMock(null, true);
        $transactionManager = $this->transactionManagerMock();
        $markCampaignAsDeletedService = new MarkedCampaignsAsDeleted($repository);

        $transactionManager
            ->expects($this->never())
            ->method('begin');

        $inventoryImporter = new InventoryImporter(
            $markCampaignAsDeletedService,
            $repository,
            $demandClient,
            $transactionManager
        );

        $inventoryImporter->import('localhost:8101');

        $this->doesNotPerformAssertions();
    }

    private function repositoryMock()
    {
        $repository = $this->createMock(CampaignRepository::class);

        return $repository;
    }

    private function clientMock(?CampaignCollection $campaigns = null, bool $badResponse = false)
    {
        $client = $this->createMock(DemandClient::class);

        if ($badResponse) {
            $client
                ->expects($this->once())
                ->method('fetchAllInventory')
                ->will($this->throwException(new UnexpectedClientResponseException()));

            return $client;
        }

        if (null === $campaigns) {
            $client
                ->expects($this->once())
                ->method('fetchAllInventory')
                ->will($this->throwException(new EmptyInventoryException()));
        } else {
            $client
                ->expects($this->once())
                ->method('fetchAllInventory')
                ->willReturn($campaigns);
        }

        return $client;
    }

    private function transactionManagerMock()
    {
        $transactionManager = $this->createMock(TransactionManager::class);

        return $transactionManager;
    }

    public function testImportWhenMarkedCampaignsServiceThrowsAnException()
    {
        $inMemoryDemandClient = new DummyDemandClient();
        $campaigns = new CampaignCollection(...$inMemoryDemandClient->campaigns);

        $repository = $this->repositoryMock();
        $repository
            ->expects($this->once())
            ->method('markedAsDeletedByHost')
            ->will($this->throwException(new CampaignRepositoryException()));
        $repository
            ->expects($this->never())
            ->method('save');


        $demandClient = $this->clientMock($campaigns);
        $transactionManager = $this->transactionManagerMock();
        $markCampaignAsDeletedService = new MarkedCampaignsAsDeleted($repository);

        $inventoryImporter = new InventoryImporter(
            $markCampaignAsDeletedService,
            $repository,
            $demandClient,
            $transactionManager
        );

        $inventoryImporter->import('localhost:8101');

        $statuses = array_map(function ($item) {
            return $item->getStatus();
        }, $inMemoryDemandClient->campaigns);

        $this->assertEquals(Status::STATUS_PROCESSING, $statuses[0]);
        $this->assertEquals(Status::STATUS_PROCESSING, $statuses[1]);
    }

    public function testImportWhenActivateIsSuccessful()
    {
        $inMemoryDemandClient = new DummyDemandClient();
        $campaigns = new CampaignCollection(...$inMemoryDemandClient->campaigns);

        $repository = $this->repositoryMock();
        $repository
            ->expects($this->once())
            ->method('markedAsDeletedByHost');
        $repository
            ->expects($this->exactly(2))
            ->method('save');


        $demandClient = $this->clientMock($campaigns);
        $transactionManager = $this->transactionManagerMock();
        $markCampaignAsDeletedService = new MarkedCampaignsAsDeleted($repository);

        $inventoryImporter = new InventoryImporter(
            $markCampaignAsDeletedService,
            $repository,
            $demandClient,
            $transactionManager
        );

        $inventoryImporter->import('localhost:8101');

        $statuses = array_map(function ($item) {
            return $item->getStatus();
        }, $inMemoryDemandClient->campaigns);

        $this->assertEquals(Status::STATUS_ACTIVE, $statuses[0]);
        $this->assertEquals(Status::STATUS_ACTIVE, $statuses[1]);
    }
}
