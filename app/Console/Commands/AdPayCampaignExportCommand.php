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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Client\Mapper\AdPay\DemandBidStrategyMapper;
use Adshares\Adserver\Client\Mapper\AdPay\DemandCampaignMapper;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;

use function count;
use function sprintf;

class AdPayCampaignExportCommand extends BaseCommand
{
    private const BID_STRATEGY_CHUNK_SIZE = 100;

    private const CAMPAIGN_CHUNK_SIZE = 100;

    protected $signature = 'ops:adpay:campaign:export';

    protected $description = 'Exports campaign data to AdPay';

    /** @var AdPay */
    private $adPay;

    public function __construct(Locker $locker, AdPay $adPay)
    {
        $this->adPay = $adPay;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('Start command ' . $this->signature);

        $this->exportBidStrategies();
        $this->exportCampaigns();

        $this->info('Finish command ' . $this->signature);
    }

    private function exportBidStrategies(): void
    {
        $now = new DateTime();
        $dateFrom = Config::fetchDateTime(Config::ADPAY_BID_STRATEGY_EXPORT_TIME);

        $offset = 0;
        $loopCount = 0;
        do {
            $bigStrategies = BidStrategy::fetchForExport($dateFrom, self::BID_STRATEGY_CHUNK_SIZE, $offset);
            $bidStrategiesCount = $bigStrategies->count();
            $this->info(sprintf('(%d) Found %d bid strategies to export.', ++$loopCount, $bidStrategiesCount));

            if ($bidStrategiesCount > 0) {
                $this->adPay->updateBidStrategies(
                    DemandBidStrategyMapper::mapBidStrategyCollectionToArray($bigStrategies)
                );
            }

            $offset += $bidStrategiesCount;
        } while ($bidStrategiesCount === self::BID_STRATEGY_CHUNK_SIZE);

        $deletedBidStrategies = BidStrategy::onlyTrashed()->where('updated_at', '>=', $dateFrom)->get();
        $this->info(sprintf('Found %d deleted bid strategies to export.', count($deletedBidStrategies)));
        if (count($deletedBidStrategies) > 0) {
            $bidStrategyIds = DemandBidStrategyMapper::mapBidStrategyCollectionToIds($deletedBidStrategies);
            $this->adPay->deleteBidStrategies($bidStrategyIds);
        }

        Config::upsertDateTime(Config::ADPAY_BID_STRATEGY_EXPORT_TIME, $now);
    }

    private function exportCampaigns(): void
    {
        $now = new DateTime();
        $dateFrom = Config::fetchDateTime(Config::ADPAY_CAMPAIGN_EXPORT_TIME);

        $offset = 0;
        $loopCount = 0;
        do {
            $updatedCampaigns =
                Campaign::where('updated_at', '>=', $dateFrom)->where('status', Campaign::STATUS_ACTIVE)->with(
                    'conversions'
                )->limit(self::CAMPAIGN_CHUNK_SIZE)->offset($offset)->get();
            $campaignCount = $updatedCampaigns->count();
            $this->info(sprintf('(%d) Found %d updated campaigns to export.', ++$loopCount, $campaignCount));

            if ($campaignCount > 0) {
                $campaigns = DemandCampaignMapper::mapCampaignCollectionToCampaignArray($updatedCampaigns);
                $this->adPay->updateCampaign($campaigns);
            }

            $offset += $campaignCount;
        } while ($campaignCount === self::CAMPAIGN_CHUNK_SIZE);

        $deletedCampaigns =
            Campaign::withTrashed()
                ->where('updated_at', '>=', $dateFrom)
                ->whereIn('status', [Campaign::STATUS_INACTIVE, Campaign::STATUS_SUSPENDED])
                ->get();
        $this->info(sprintf('Found %d deleted campaigns to export.', count($deletedCampaigns)));
        if (count($deletedCampaigns) > 0) {
            $campaignIds = DemandCampaignMapper::mapCampaignCollectionToCampaignIds($deletedCampaigns);
            $this->adPay->deleteCampaign($campaignIds);
        }

        Config::upsertDateTime(Config::ADPAY_CAMPAIGN_EXPORT_TIME, $now);
    }
}
