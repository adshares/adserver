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
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;

class NetworkCampaignRepository implements CampaignRepository
{

    public function markedAsDeletedByHost(string $host): void
    {
        $campaignIdsResult = DB::select(
            sprintf('select id from %s where source_host = ?', NetworkCampaign::getTableName()),
            [
                $host,
            ]
        );

        if (!$campaignIdsResult) {
            return;
        }

        $campaignIds = [];
        foreach ($campaignIdsResult as $campaignId) {
            $campaignIds[] = $campaignId->id;
        }

        DB::table(NetworkBanner::getTableName())
            ->whereIn('network_campaign_id', $campaignIds)
            ->delete()
            ;

        DB::table(NetworkCampaign::getTableName())
            ->whereIn('id', $campaignIds)
            ->delete()
        ;

//        DB::update(
//            sprintf('update %s set status = ? where source_host = ?', NetworkCampaign::getTableName()),
//            [
//                Campaign::STATUS_DELETED,
//                $host
//            ]
//        );
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
        $networkCampaign->demand_campaign_id = $campaign->getDemandCampaignId();
        $networkCampaign->landing_url = $campaign->getLandingUrl();

        $networkCampaign->max_cpc = $campaign->getMaxCpc();
        $networkCampaign->max_cpm = $campaign->getMaxCpm();
        $networkCampaign->budget_per_hour = $campaign->getBudget();

        $networkCampaign->source_host = $campaign->getSourceHost();
        $networkCampaign->source_created_at = $campaign->getCreatedAt();
        $networkCampaign->source_updated_at = $campaign->getUpdatedAt();
        $networkCampaign->adshares_address = $campaign->getSourceAddress();
        $networkCampaign->source_version = $campaign->getSourceVersion();

        $networkCampaign->time_start = $campaign->getDateStart();
        $networkCampaign->time_end = $campaign->getDateEnd();

        $networkCampaign->targeting_requires = $campaign->getTargetingRequires();
        $networkCampaign->targeting_excludes = $campaign->getTargetingExcludes();

        $networkCampaign->save();

        $networkCampaign->banners()->saveMany($networkBanners);
    }

    public function fetchActiveCampaigns(): CampaignCollection
    {
        $networkCampaigns = (new NetworkCampaign())->get();

        $campaigns = [];

        foreach ($networkCampaigns as $networkCampaign) {
            $campaigns[] = $this->createDomainCampaignFromNetworkCampaign($networkCampaign);
        }

        return new CampaignCollection(...$campaigns);
    }

    private function createDomainCampaignFromNetworkCampaign(NetworkCampaign $networkCampaign): Campaign
    {
        $banners = [];

        foreach ($networkCampaign->banners as $networkBanner) {
            $banners[] = [
                'serve_url' => $networkBanner->serve_url,
                'click_url' => $networkBanner->click_url,
                'view_url' => $networkBanner->view_url,
                'type' => $networkBanner->creative_type,
                'width' => $networkBanner->creative_width,
                'height' => $networkBanner->creative_height,
            ];
        }

        return CampaignFactory::createFromArray([
            'id' => 1,
            'uuid' => Uuid::fromString($networkCampaign->uuid),
            'user_id' => 1, //$networkCampaign->user_id, @todo make user_id uuid
            'landing_url' => $networkCampaign->landing_url,
            'date_start' => $networkCampaign->time_start,
            'date_end' => $networkCampaign->time_end,
            'created_at' => $networkCampaign->created_at,
            'updated_at' => $networkCampaign->updated_at,
            'source_host' => [
                'host' => 'localhost:8101',
                'address' => '0001-00000001-0001',
                'version' => '0.1',
            ],
            'banners' => $banners,
            'max_cpc' => $networkCampaign->max_cpc,
            'max_cpm' => $networkCampaign->max_cpm,
            'budget' => $networkCampaign->budget_per_hour,
            'targeting_excludes' => $networkCampaign->targeting_excludes,
            'targeting_requires' => $networkCampaign->targeting_requires,
        ]);
    }
}
