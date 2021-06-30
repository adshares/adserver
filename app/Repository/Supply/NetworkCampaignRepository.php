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

namespace Adshares\Adserver\Repository\Supply;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Domain\ValueObject\Status;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

use function hex2bin;

class NetworkCampaignRepository implements CampaignRepository
{
    private const STATUS_FIELD = 'status';

    private const CAMPAIGN_UUID_FIELD = 'uuid';

    private const BANNER_CLICK_URL_FIELD = 'click_url';

    private const BANNER_SERVE_URL_FIELD = 'serve_url';

    private const BANNER_VIEW_URL_FIELD = 'view_url';

    private const BANNER_UUID_FIELD = 'uuid';

    private const BANNER_ID_FIELD = 'id';

    private const BANNER_DEMAND_BANNER_ID_FIELD = 'demand_banner_id';

    private const BANNER_TYPE_FIELD = 'type';

    private const BANNER_SZIE_FIELD = 'size';

    private const BANNER_CLASSIFICATION_FIELD = 'classification';

    public function markedAsDeletedBySourceAddress(AccountId $sourceAddress): void
    {
        $address = $sourceAddress->toString();

        DB::table(NetworkCampaign::getTableName())
            ->where('source_address', $address)
            ->whereNotIn(self::STATUS_FIELD, [Status::STATUS_DELETED])
            ->update([self::STATUS_FIELD => Status::STATUS_TO_DELETE]);

        DB::table(sprintf('%s as banner', NetworkBanner::getTableName()))
            ->join(
                sprintf('%s as campaign', NetworkCampaign::getTableName()),
                'banner.network_campaign_id',
                '=',
                'campaign.id'
            )
            ->where('campaign.source_address', $address)
            ->where('campaign.' . self::STATUS_FIELD, Status::STATUS_TO_DELETE)
            ->update(['banner.status' => Status::STATUS_TO_DELETE]);
    }

    public function save(Campaign $campaign): void
    {
        $campaignArray = $campaign->toArray();
        $uuid = $campaignArray['id'];
        unset($campaignArray['id']);

        $networkCampaign = $this->fetchCampaignByDemand($campaign);

        if (!$networkCampaign) {
            $networkCampaign = new NetworkCampaign();
            $networkCampaign->uuid = $uuid;
        }

        $networkCampaign->fill($campaignArray);

        try {
            $networkCampaign->save();
        } catch (QueryException $exception) {
            Log::debug(sprintf(
                '%s - %s',
                $exception->getMessage(),
                $exception->getSql()
            ));

            throw $exception;
        }

        $banners = $campaign->getBanners();
        $networkBanners = [];

        /** @var Banner $domainBanner */
        foreach ($banners as $domainBanner) {
            $banner = $domainBanner->toArray();
            $banner[self::BANNER_UUID_FIELD] = $banner[self::BANNER_ID_FIELD];
            $banner[self::BANNER_SERVE_URL_FIELD] = SecureUrl::change($banner[self::BANNER_SERVE_URL_FIELD]);
            $banner[self::BANNER_CLICK_URL_FIELD] = SecureUrl::change($banner[self::BANNER_CLICK_URL_FIELD]);
            $banner[self::BANNER_VIEW_URL_FIELD] = SecureUrl::change($banner[self::BANNER_VIEW_URL_FIELD]);

            unset($banner['id']);

            $networkBanner = NetworkBanner::where('demand_banner_id', hex2bin($domainBanner->getDemandBannerId()))
                ->where('network_campaign_id', $networkCampaign->id)->first();

            if (!$networkBanner) {
                $networkBanner = new NetworkBanner();
            }

            $networkBanner->fill($banner);

            $networkBanners[] = $networkBanner;
        }

        $networkCampaign->banners()->saveMany($networkBanners);
    }

    private function fetchCampaignByDemand(Campaign $campaign): ?NetworkCampaign
    {
        return NetworkCampaign::where('demand_campaign_id', hex2bin($campaign->getDemandCampaignId()))
            ->where('source_address', $campaign->getSourceAddress())
            ->first();
    }

    public function fetchActiveCampaigns(): CampaignCollection
    {
        $networkCampaigns = NetworkCampaign::where(self::STATUS_FIELD, Status::STATUS_ACTIVE)->get();

        $campaigns = [];

        foreach ($networkCampaigns as $networkCampaign) {
            $campaigns[] = $this->createDomainCampaignFromNetworkCampaign($networkCampaign);
        }

        return new CampaignCollection(...$campaigns);
    }

    public function fetchCampaignsToDelete(): CampaignCollection
    {
        $networkCampaigns = NetworkCampaign::where(self::STATUS_FIELD, Status::STATUS_TO_DELETE)->get();

        $campaigns = [];

        foreach ($networkCampaigns as $networkCampaign) {
            $campaigns[] = $this->createDomainCampaignFromNetworkCampaign($networkCampaign);
        }

        return new CampaignCollection(...$campaigns);
    }

    private function createDomainCampaignFromNetworkCampaign(NetworkCampaign $networkCampaign): Campaign
    {
        $banners = [];

        foreach ($networkCampaign->fetchActiveBanners() as $networkBanner) {
            $banners[] = [
                self::BANNER_ID_FIELD => Uuid::fromString($networkBanner->uuid),
                self::BANNER_DEMAND_BANNER_ID_FIELD => Uuid::fromString($networkBanner->demand_banner_id),
                self::BANNER_SERVE_URL_FIELD => $networkBanner->serve_url,
                self::BANNER_CLICK_URL_FIELD => $networkBanner->click_url,
                self::BANNER_VIEW_URL_FIELD => $networkBanner->view_url,
                self::BANNER_TYPE_FIELD => $networkBanner->type,
                self::BANNER_SZIE_FIELD => $networkBanner->size,
                self::STATUS_FIELD => $networkBanner->status,
                self::BANNER_CLASSIFICATION_FIELD => $networkBanner->classification,
            ];
        }

        return CampaignFactory::createFromArray(
            [
                'id' => Uuid::fromString($networkCampaign->uuid),
                'demand_id' => Uuid::fromString($networkCampaign->demand_campaign_id),
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
                'max_cpc' => (int)$networkCampaign->max_cpc,
                'max_cpm' => (int)$networkCampaign->max_cpm,
                'budget' => (int)$networkCampaign->budget,
                'targeting_excludes' => $networkCampaign->targeting_excludes ?? [],
                'targeting_requires' => $networkCampaign->targeting_requires ?? [],
                self::STATUS_FIELD => $networkCampaign->status,
            ]
        );
    }

    public function deleteCampaign(Campaign $campaign): void
    {
        $campaign->delete();

        DB::beginTransaction();

        try {
            DB::table(NetworkCampaign::getTableName())
                ->where(self::CAMPAIGN_UUID_FIELD, hex2bin($campaign->getId()))
                ->update([self::STATUS_FIELD => $campaign->getStatus()]);

            DB::table(sprintf('%s as banner', NetworkBanner::getTableName()))
                ->join(
                    sprintf('%s as campaign', NetworkCampaign::getTableName()),
                    'banner.network_campaign_id',
                    '=',
                    'campaign.id'
                )
                ->where('campaign.uuid', hex2bin($campaign->getId()))
                ->update(['banner.status' => Status::STATUS_DELETED]);
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();
    }
}
