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
declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Client\AdPayClient;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class AdPayCampaignExportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'adpay:campaign:export';

    /**
     * @var string
     */
    protected $description = 'Exports campaign data to AdPay';

    /**
     * @param AdPayClient $adPayClient
     *
     * @throws \Exception
     */
    public function handle(AdPayClient $adPayClient)
    {
        $this->info('Start command '.$this->signature);

        $configDate = Config::where('key', Config::AD_PAY_CAMPAIGN_EXPORT_TIME)->first();
        if (null === $configDate) {
            $configDate = new Config();
            $configDate->key = Config::AD_PAY_CAMPAIGN_EXPORT_TIME;

            $dateFrom = new DateTime('@0');
        } else {
            $dateFrom = DateTime::createFromFormat(DATE_ATOM, $configDate->value);
        }

        $dateNow = new DateTime();

        $updatedCampaigns = Campaign::where('updated_at', '>=', $dateFrom)->get();
        if (count($updatedCampaigns) > 0) {
            $campaigns = $this->mapCampaignCollectionToCampaignArray($updatedCampaigns);
            $adPayClient->updateCampaign($campaigns);
        }

        $deletedCampaigns = Campaign::onlyTrashed()->where('updated_at', '>=', $dateFrom)->get();
        if (count($deletedCampaigns) > 0) {
            $campaignIds = $this->mapCampaignCollectionToCampaignIds($deletedCampaigns);
            $adPayClient->deleteCampaign($campaignIds);
        }

        $configDate->value = $dateNow->format(DATE_ATOM);
        $configDate->save();

        $this->info('Finish command '.$this->signature);
    }

    /**
     * @param $campaigns
     *
     * @return array
     */
    public function mapCampaignCollectionToCampaignArray($campaigns): array
    {
        $campaignArray = $campaigns->map(
            function (Campaign $campaign) {
                $mappedAds = [];
                $ads = $campaign->ads;
                foreach ($ads as $ad) {
                    $mappedAds[] = [
                        'uuid' => $ad->uuid,
                        'status' => $ad->status,
                    ];
                }

                $mapped = [];
                $mapped['uuid'] = $campaign->uuid;
                $mapped['status'] = $campaign->basic_information['status'];
                $mapped['time_start'] = DateTime::createFromFormat(DATE_ATOM, $campaign->time_start)->getTimestamp();
                $mapped['time_end'] =
                    ($campaign->time_end === null)
                        ? null : DateTime::createFromFormat(DATE_ATOM, $campaign->time_end)
                        ->getTimestamp();
                $mapped['ads'] = $mappedAds;

                return $mapped;
            }
        )->toArray();

        return $campaignArray;
    }

    /**
     * @param $campaigns
     *
     * @return array
     */
    public function mapCampaignCollectionToCampaignIds(Collection $campaigns): array
    {
        $campaignIds = [];
        foreach ($campaigns as $deletedCampaign) {
            $campaignIds[] = $deletedCampaign->uuid;
        }

        return $campaignIds;
    }
}
