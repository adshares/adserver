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

namespace Adshares\Test\Supply\Application\Service;

use Adshares\Common\Application\TransactionManager;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Dto\Classification\Collection;
use Adshares\Supply\Application\Service\BannerClassifier;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\InventoryImporter;
use Adshares\Supply\Application\Service\MarkedCampaignsAsDeleted;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Repository\Exception\CampaignRepositoryException;
use Adshares\Supply\Domain\ValueObject\Status;
use PHPUnit\Framework\TestCase;

final class InventoryImporterTest extends TestCase
{
    public function testImportWhenDemandClientReturnsUnexpectedResponse(): void
    {
        $this->expectException(UnexpectedClientResponseException::class);

        $repository = $this->repositoryMock();
        $demandClient = $this->clientMock(null, true);
        $transactionManager = $this->transactionManagerMock();
        $markCampaignAsDeletedService = new MarkedCampaignsAsDeleted($repository);
        $classifierClient = $this->classifierClientMock();

        $transactionManager
            ->expects($this->never())
            ->method('begin');

        $inventoryImporter = new InventoryImporter(
            $markCampaignAsDeletedService,
            $repository,
            $demandClient,
            $classifierClient,
            $transactionManager
        );

        $inventoryImporter->import(
            new AccountId('0001-00000001-8B4E'),
            'localhost:8101',
            'http://localhost:8101/inventory/list'
        );

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

    public function testImportWhenMarkedCampaignsServiceThrowsAnException(): void
    {
        $inMemoryDemandClient = new DummyDemandClient();
        $campaigns = new CampaignCollection(...$inMemoryDemandClient->campaigns);
        $classifierClient = $this->classifierClientMock();

        $repository = $this->repositoryMock();
        $repository
            ->expects($this->once())
            ->method('markedAsDeletedBySourceAddress')
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
            $classifierClient,
            $transactionManager
        );

        $inventoryImporter->import(
            new AccountId('0001-00000001-8B4E'),
            'localhost:8101',
            'http://localhost:8101/inventory/list'
        );

        $statuses = array_map(function ($item) {
            return $item->getStatus();
        }, $inMemoryDemandClient->campaigns);

        $this->assertEquals(Status::STATUS_PROCESSING, $statuses[0]);
        $this->assertEquals(Status::STATUS_PROCESSING, $statuses[1]);
    }

    public function testImportWhenActivateIsSuccessful(): void
    {
        $inMemoryDemandClient = new DummyDemandClient();
        $campaigns = new CampaignCollection(...$inMemoryDemandClient->campaigns);

        $repository = $this->repositoryMock();
        $repository
            ->expects($this->once())
            ->method('markedAsDeletedBySourceAddress');
        $repository
            ->expects($this->exactly(2))
            ->method('save');


        $demandClient = $this->clientMock($campaigns);
        $transactionManager = $this->transactionManagerMock();
        $markCampaignAsDeletedService = new MarkedCampaignsAsDeleted($repository);
        $classifierClient = $this->classifierClientMock();

        $inventoryImporter = new InventoryImporter(
            $markCampaignAsDeletedService,
            $repository,
            $demandClient,
            $classifierClient,
            $transactionManager
        );

        $inventoryImporter->import(
            new AccountId('0001-00000001-8B4E'),
            'localhost:8101',
            'http://localhost:8101/inventory/list'
        );

        $statuses = array_map(function ($item) {
            return $item->getStatus();
        }, $inMemoryDemandClient->campaigns);

        $this->assertEquals(Status::STATUS_ACTIVE, $statuses[0]);
        $this->assertEquals(Status::STATUS_ACTIVE, $statuses[1]);
    }

    public function classifierClientMock(array $bannerIds = [])
    {
        $client = $this->createMock(BannerClassifier::class);

        $client
            ->method('fetchBannersClassification')
            ->willReturn(new Collection([]));

            return $client;
    }
}
