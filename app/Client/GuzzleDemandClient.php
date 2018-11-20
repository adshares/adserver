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

namespace Adshares\Adserver\Client;

use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Service\DemandClient;
use GuzzleHttp\Client;

class GuzzleDemandClient implements DemandClient
{
    const VERSION = '0.1';

    const ALL_INVENTORY_ENDPOINT = '/adshares/inventory/list';

    public function fetchAllInventory(string $inventoryHost): CampaignCollection
    {
        $client = new Client([
            'base_uri' => $inventoryHost,
            'timeout'  => 5.0,
        ]);

        $response = $client->get(self::ALL_INVENTORY_ENDPOINT);

    }
}
