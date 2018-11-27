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

        /** @var Banner $domainBanner */
        foreach ($banners as $domainBanner) {
            $banner = $domainBanner->toArray();
            $banner['uuid'] = $banner['id'];
            unset($banner['id']);

            $networkBanners[] = new NetworkBanner($banner);
        }

        $campaign = $campaign->toArray();
        $campaign['uuid'] = $campaign['id'];
        unset($campaign['id']);

        $networkCampaign = new NetworkCampaign($campaign);

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
                'type' => $networkBanner->type,
                'width' => $networkBanner->width,
                'height' => $networkBanner->height,
            ];
        }

        return CampaignFactory::createFromArray([
            'uuid' => Uuid::fromString($networkCampaign->uuid),
            'publisher_id' => Uuid::fromString($networkCampaign->publisher_id),
            'landing_url' => $networkCampaign->landing_url,
            'date_start' => $networkCampaign->date_start,
            'date_end' => $networkCampaign->date_end,
            'source_campaign' => [
                'host' => $networkCampaign->source_host,
                'address' => $networkCampaign->source_address,
                'version' => $networkCampaign->source_version,
                'created_at' => $networkCampaign->source_created_at,
                'updated_at' => $networkCampaign->source_updated_at,
            ],
            'created_at' => $networkCampaign->created_at,
            'updated_at' => $networkCampaign->updated_at,
            'banners' => $banners,
            'max_cpc' => (float)$networkCampaign->max_cpc,
            'max_cpm' => (float)$networkCampaign->max_cpm,
            'budget' => (float)$networkCampaign->budget,
            'targeting_excludes' => $networkCampaign->targeting_excludes ?? [],
            'targeting_requires' => $networkCampaign->targeting_requires ?? [],
        ]);
    }
}
