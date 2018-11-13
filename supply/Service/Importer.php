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

declare(strict_types = 1);

namespace Adshares\Supply\Service;

use Adshares\Supply\Model\InventoryServer;
use Adshares\Supply\Repository\InventoryRepository;

final class Importer
{
    /** @var InventoryRepository */
    private $inventoryRepository;

    /*** @var DemandClient */
    private $client;

    public function __construct(InventoryRepository $inventoryRepository, DemandClient $client)
    {
        $this->inventoryRepository = $inventoryRepository;
        $this->client = $client;
    }

    public function import(InventoryServer $inventoryServer): void
    {
        $inventory = $this->client->fetchInventory($inventoryServer);

        foreach ($inventory->getCampaigns() as $campaign) {
            $this->inventoryRepository->save($campaign);
        }
    }
}
