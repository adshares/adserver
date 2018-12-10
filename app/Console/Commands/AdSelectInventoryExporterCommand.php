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

use Adshares\Adserver\Repository\Supply\NetworkCampaignRepository;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use Adshares\Supply\Application\Service\Exception\NoBannersForGivenCampaign;
use Adshares\Supply\Domain\Model\Campaign;
use Illuminate\Console\Command;

class AdSelectInventoryExporterCommand extends Command
{
    protected $signature = 'ops:inventory:export';

    protected $description = 'Export campaigns inventory to AdSelect';

    private $inventoryExporterService;

    /** @var NetworkCampaignRepository */
    private $campaignRepository;

    public function __construct(
        AdSelectInventoryExporter $inventoryExporterService,
        NetworkCampaignRepository $campaignRepository
    ) {
        $this->inventoryExporterService = $inventoryExporterService;
        $this->campaignRepository = $campaignRepository;

        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting export inventory to AdSelect.');

        $campaigns = $this->campaignRepository->fetchActiveCampaigns();

        if (!$campaigns) {
            $this->info('Stopped exporting. No campaigns found.');

            return;
        }

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            try {
                $this->inventoryExporterService->export($campaign);
            } catch (NoBannersForGivenCampaign $exception) {
                // skip campaign without banners
            }
        }
    }
}
