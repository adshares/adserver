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

namespace Adshares\Adserver\Repository\Supply;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Repository\CampaignRepository;

class NetworkCampaignRepository implements CampaignRepository
{

    public function deactivateAllCampaignsFromHost(string $host): void
    {
        // TODO: Implement deactivateAllCampaignsFromHost() method.
    }

    public function save(Campaign $campaign): void
    {
        $banners = $campaign->getBanners();

        $networkBanners = [];

        /** @var Banner $banner */
        foreach ($banners as $banner) {
            $bannerUrl = $banner->getBannerUrl();

            $networkBanner = new NetworkBanner();
            $networkBanner->uuid = $banner->getId();
            $networkBanner->serve_url = $bannerUrl->getServeUrl();
            $networkBanner->click_url = $bannerUrl->getClickUrl();
            $networkBanner->view_url = $bannerUrl->getViewUrl();
            $networkBanner->creative_type = $banner->getType();
            $networkBanner->creative_width = $banner->getWidth();
            $networkBanner->creative_height = $banner->getHeight();

            $networkBanners[] = $networkBanner;
        }

        $networkCampaign = new NetworkCampaign();
        $networkCampaign->uuid = $campaign->getId();
//        $networkCampaign->$networkBanner->network_campaign_id = $banner->getCampaignId();
    }
}
