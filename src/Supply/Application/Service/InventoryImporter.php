<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Supply\Application\Dto\Classification\Collection;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Repository\Exception\CampaignRepositoryException;
use Adshares\Supply\Domain\ValueObject\Classification;

class InventoryImporter
{
    public function __construct(
        private readonly MarkedCampaignsAsDeleted $markedCampaignsAsDeletedService,
        private readonly CampaignRepository $campaignRepository,
        private readonly DemandClient $client,
        private readonly BannerClassifier $classifyClient,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    public function import(
        AccountId $sourceAddress,
        string $sourceHost,
        string $inventoryHost,
        bool $isAdsTxtRequiredBySourceHost,
    ): void {
        $campaigns = $this->client->fetchAllInventory(
            $sourceAddress,
            $sourceHost,
            $inventoryHost,
            $isAdsTxtRequiredBySourceHost,
        );
        $bannersPublicIds = $this->getBannerIds($campaigns);
        $classificationCollection = $this->classifyClient->fetchBannersClassification($bannersPublicIds);

        $this->transactionManager->begin();

        try {
            $this->clearInventoryForHostAddress($sourceAddress);

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
        foreach ($campaign->getBanners() as $banner) {
            $classifications = $classificationCollection->findByBannerId($banner->getId()) ?? [];

            /** @var Classification $classification */
            foreach ($classifications as $classification) {
                $banner->classify($classification);
            }
        }
    }

    public function clearInventoryForHostAddress(AccountId $sourceAddress): void
    {
        $this->markedCampaignsAsDeletedService->execute($sourceAddress);
    }
}
