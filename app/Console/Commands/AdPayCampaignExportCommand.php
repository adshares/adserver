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

use Adshares\Adserver\Client\Mapper\AdPay\DemandCampaignMapper;
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;
use Illuminate\Console\Command;

class AdPayCampaignExportCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:adpay:campaign:export';

    protected $description = 'Exports campaign data to AdPay';

    public function handle(AdPay $adPay): void
    {
        $this->info('Start command '.$this->signature);

        $configDate = Config::where('key', Config::ADPAY_CAMPAIGN_EXPORT_TIME)->first();
        if (null === $configDate) {
            $configDate = new Config();
            $configDate->key = Config::ADPAY_CAMPAIGN_EXPORT_TIME;

            $dateFrom = new DateTime('@0');
        } else {
            $dateFrom = DateTime::createFromFormat(DATE_ATOM, $configDate->value);
        }

        $dateNow = new DateTime();

        $updatedCampaigns = Campaign::where('updated_at', '>=', $dateFrom)->get();
        if (count($updatedCampaigns) > 0) {
            $campaigns = DemandCampaignMapper::mapCampaignCollectionToCampaignArray($updatedCampaigns);
            $adPay->updateCampaign($campaigns);
        }

        $deletedCampaigns = Campaign::onlyTrashed()->where('updated_at', '>=', $dateFrom)->get();
        if (count($deletedCampaigns) > 0) {
            $campaignIds = DemandCampaignMapper::mapCampaignCollectionToCampaignIds($deletedCampaigns);
            $adPay->deleteCampaign($campaignIds);
        }

        $configDate->value = $dateNow->format(DATE_ATOM);
        $configDate->save();

        $this->info('Finish command '.$this->signature);
    }
}
