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

use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\Service\DemandClient;

class InventoryImporter
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

    public function import(string $host): void
    {
        $inventory = $this->client->fetchAllInventory($host);

        foreach ($inventory->getCampaigns() as $campaign) {
            try {
                $this->campaignRepository->save($campaign);
            } catch (\Exception $ex) {
                // delete old banners ???
                // inventory przesyła wszystkie dane, my możemy jakieś już mieć. Co wtedy?
                // a) Kasujemy stare banery i dodajemy tylko nowe? Tak raczej nie można.
                // b) Dodajemy lub updatujemy nowe? Jeśli nie ma to kasujemy?
                $this->campaignRepository->update($campaign);
            }
        }
    }

//    public function import(DemandServer $demandServer): void
//    {
//        $inventory = $this->client->fetchInventory($demandServer);
//
//        foreach ($inventory->getCampaigns() as $campaign) {
//            $this->campaignRepository->save($campaign);
//        }
//    }
}
