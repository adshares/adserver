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

declare(strict_types = 1);

namespace Adshares\Supply\Application\Service;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Common\Application\TransactionManager;
use Adshares\Supply\Application\Dto\Classification\Collection;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Repository\Exception\CampaignRepositoryException;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Domain\ValueObject\Classification;
use function array_key_exists;
use function array_search;

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

    /** @var BannerClassifier */
    private $classifyClient;

    /** @var ClassifyVerifier */
    private $classifyVerifier;

    public function __construct(
        MarkedCampaignsAsDeleted $markedCampaignsAsDeletedService,
        CampaignRepository $campaignRepository,
        DemandClient $client,
        BannerClassifier $classifyClient,
        ClassifyVerifier $classifyVerifier,
        TransactionManager $transactionManager
    ) {
        $this->client = $client;
        $this->campaignRepository = $campaignRepository;
        $this->transactionManager = $transactionManager;
        $this->markedCampaignsAsDeletedService = $markedCampaignsAsDeletedService;
        $this->classifyClient = $classifyClient;
        $this->classifyVerifier = $classifyVerifier;
    }

    public function import(string $sourceHost, string $inventoryHost): void
    {
        $campaigns = $this->client->fetchAllInventory($sourceHost, $inventoryHost);
        $bannersPublicIds = $this->getBannerIds($campaigns);
        $classificationCollection = $this->classifyClient->fetchBannersClassification($bannersPublicIds);

        $this->transactionManager->begin();

        try {
            $this->clearInventoryForHost($sourceHost);

            /** @var Campaign $campaign */
            foreach ($campaigns as $campaign) {
                $campaign->activate();
                $this->classifyCampaign($campaign, $classificationCollection);

                $this->campaignRepository->save($campaign);
            }
        } catch (CampaignRepositoryException $exception) {
            $this->transactionManager->rollback();
        }

        $this->transactionManager->commit();
    }

    private function getBannerIds(CampaignCollection $campaigns): array
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

    private function classifyCampaign(
        Campaign $campaign,
        Collection $classificationCollection
    ): void {
        /** @var Banner $banner */
        foreach ($campaign->getBanners() as $banner) {
            $classifications = $classificationCollection->findByBannerId($banner->getId()) ?? [];

            if (empty($classifications)) {
                $banner->unclassified();
            }

            /** @var Classification $classification */
            foreach ($classifications as $classification) {
                if ($classification && $this->classifyVerifier->isVerified($classification, $banner->getId())) {
                    $banner->classify($classification);
                } else {
                    $banner->removeClassification($classification);
                }
            }
        }
    }

    public function clearInventoryForHost(string $host): void
    {
        $this->markedCampaignsAsDeletedService->execute($host);
    }
}
