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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Repository\CampaignRepository;

class NetworkCampaignRepository implements CampaignRepository
{

    public function markedAsDeletedByHost(string $host): void
    {
        DB::update(
            sprintf('update %s set status = ? where source_host = ?', NetworkCampaign::getTableName()),
            [
                Campaign::STATUS_DELETED,
                $host
            ]
        );

//        throw new CampaignRepositoryException('tetete');
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
        $networkCampaign->parent_uuid = $campaign->getParentId();
        $networkCampaign->landing_url = $campaign->getLandingUrl();

        $networkCampaign->max_cpc = $campaign->getMaxCpc();
        $networkCampaign->max_cpm = $campaign->getMaxCpm();
        $networkCampaign->budget_per_hour = $campaign->getBudget();

        $networkCampaign->source_host = $campaign->getSourceHost();
        $networkCampaign->source_created_at = $campaign->getSourceCreatedAt();
        $networkCampaign->source_updated_at = $campaign->getSourceUpdatedAt();
        $networkCampaign->adshares_address = $campaign->getSourceAddress();
        $networkCampaign->source_version = $campaign->getSourceVersion();

        $networkCampaign->time_start = $campaign->getDateStart();
        $networkCampaign->time_end = $campaign->getDateEnd();

        $networkCampaign->targeting_requires = $campaign->getTargetingRequires();
        $networkCampaign->targeting_excludes = $campaign->getTargetingExcludes();

        $networkCampaign->save();

        foreach ($networkBanners as $banner) {
            $networkCampaign->banners()->save($banner);
        }
    }
}
