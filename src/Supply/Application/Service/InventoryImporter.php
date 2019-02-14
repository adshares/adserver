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

declare(strict_types=1);

namespace Adshares\Supply\Application\Service;

use Adshares\Common\Application\TransactionManager;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Dto\ClassifiedBanners;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Repository\Exception\CampaignRepositoryException;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Domain\ValueObject\Classification;

class InventoryImporter
{
    /** @var MarkedCampaignsAsDeleted */
    private $markedCampaignsAsDeletedService;

    /** @var CampaignRepository */
    private $campaignRepository;

    /** @var DemandClient */
    private $client;

    /** @var TransactionManager */
    private $transactionManager;
    /** @var ClassifierClient */
    private $classifierClient;

    public function __construct(
        MarkedCampaignsAsDeleted $markedCampaignsAsDeletedService,
        CampaignRepository $campaignRepository,
        DemandClient $client,
        ClassifierClient $classifierClient,
        TransactionManager $transactionManager
    ) {
        $this->client = $client;
        $this->campaignRepository = $campaignRepository;
        $this->transactionManager = $transactionManager;
        $this->markedCampaignsAsDeletedService = $markedCampaignsAsDeletedService;
        $this->classifierClient = $classifierClient;
    }

    public function import(string $host): void
    {
        try {
            $campaigns = $this->client->fetchAllInventory($host);
        } catch (EmptyInventoryException $exception) {
            return;
        }

        $bannersIds = $this->getBannerIds($campaigns);
        $classifiedBanners = $this->classifierClient->verify($bannersIds);

        $this->transactionManager->begin();

        try {
            $this->markedCampaignsAsDeletedService->execute($host);

            /** @var Campaign $campaign */
            foreach ($campaigns as $campaign) {
                $campaign->activate();

                /** @var Banner $banner */
                foreach ($campaign->getBanners() as $banner) {
                    $classification = $classifiedBanners->findByBannerId($banner->getId());

                    if ($classification) {
                        // verify classification here @todo
                        $banner->classify($classification);
                    }
                }

                $this->campaignRepository->save($campaign);
            }
        } catch (CampaignRepositoryException $exception) {
            $this->transactionManager->rollback();
        }

        $this->transactionManager->commit();
    }

    private function getBannerIds(CampaignCollection $campaigns)
    {
        $ids = [];

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            /** @var Banner $banner */
            foreach ($campaign->getBanners() as $banner) {
                $ids[] = $banner->getId();
            }
        }

        return $ids;
    }

    private function getClassificationByBannerId(ClassifiedBanners $classifiedBanners, string $bannerId)
    {

    }
}
