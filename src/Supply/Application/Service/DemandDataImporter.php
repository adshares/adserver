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

namespace Adshares\Supply\Application\Service;

use Adshares\Supply\Domain\Model\DemandServer;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Service\DemandClient;

class DemandDataImporter
{
    /*** @var DemandClient */
    private $client;

    /** @var CampaignRepository */
    private $campaignRepository;

    public function __construct(CampaignRepository $campaignRepository, DemandClient $client)
    {
        $this->client = $client;
        $this->campaignRepository = $campaignRepository;
    }

    public function import(DemandServer $demandServer): void
    {
        $inventory = $this->client->fetchInventory($demandServer);

        foreach ($inventory->getCampaigns() as $campaign) {
            $this->campaignRepository->save($campaign);
        }
    }
}
