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

declare(strict_types=1);

namespace Adshares\Supply\Application\Service;

use Adshares\Common\Application\TransactionManager;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Repository\Exception\CampaignRepositoryException;
use Adshares\Supply\Domain\Service\DemandClient;
use Adshares\Supply\Domain\Service\Exception\EmptyInventoryException;

class InventoryImporter
{
    /** @var MarkedCampaignsAsDeleted */
    private $markedCampaignsAsDeletedService;

    /** @var CampaignRepository */
    private $campaignRepository;

    /*** @var DemandClient */
    private $client;

    /** @var TransactionManager */
    private $transactionManager;

    public function __construct(
        MarkedCampaignsAsDeleted $markedCampaignsAsDeletedService,
        CampaignRepository $campaignRepository,
        DemandClient $client,
        TransactionManager $transactionManager
    ) {
        $this->client = $client;
        $this->campaignRepository = $campaignRepository;
        $this->transactionManager = $transactionManager;
        $this->markedCampaignsAsDeletedService = $markedCampaignsAsDeletedService;
    }

    public function import(string $host): void
    {
        try {
            $campaigns = $this->client->fetchAllInventory($host);
        } catch (EmptyInventoryException $exception) {
            return;
        }

        $this->transactionManager->begin();

        try {
            $this->markedCampaignsAsDeletedService->execute($host);

            /** @var Campaign $campaign */
            foreach ($campaigns as $campaign) {
                $campaign->activate();

                $this->campaignRepository->save($campaign);
            }
        } catch (CampaignRepositoryException $exception) {
            $this->transactionManager->rollback();
        }

        $this->transactionManager->commit();
    }
}
