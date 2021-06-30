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

namespace Adshares\Supply\Application\Service;

use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Psr\Log\LoggerInterface;

class AdSelectInventoryExporter
{
    private $client;
    /** @var CampaignRepository */
    private $repository;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(AdSelect $client, CampaignRepository $repository, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->repository = $repository;
        $this->logger = $logger;
    }

    public function export(CampaignCollection $campaignsToAddOrUpdate, CampaignCollection $campaignsToDelete): void
    {
        if (!$campaignsToAddOrUpdate->isEmpty()) {
            try {
                $this->client->exportInventory($campaignsToAddOrUpdate);
            } catch (UnexpectedClientResponseException $exception) {
                if ($exception->getCode() === 500) {
                    $this->logger->error($exception->getMessage());
                    return;
                }

                $this->logger->warning($exception->getMessage());
            }
        }

        if (!$campaignsToDelete->isEmpty()) {
            try {
                $this->client->deleteFromInventory($campaignsToDelete);

                foreach ($campaignsToDelete as $campaign) {
                    $this->repository->deleteCampaign($campaign);
                }
            } catch (UnexpectedClientResponseException $exception) {
                if ($exception->getCode() === 500) {
                    $this->logger->error($exception->getMessage());

                    return;
                }

                $this->logger->warning($exception->getMessage());
            }
        }
    }
}
