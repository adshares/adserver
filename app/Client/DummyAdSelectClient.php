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

namespace Adshares\Adserver\Client;

use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Service\AdSelectClient;
use Adshares\Supply\Domain\Model\Campaign;

class DummyAdSelectClient implements AdSelectClient
{
    public function exportInventory(Campaign $campaign): void
    {
        //AdSelectDTO
        $banners = [];

        /** @var Banner $banner */
        foreach ($campaign->getBanners() as $banner) {
            $banners[] = [
                'banner_id' => $banner->getId(),

            ];
        }

    }
}
