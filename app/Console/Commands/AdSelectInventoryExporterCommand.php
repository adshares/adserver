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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Repository\Supply\NetworkCampaignRepository;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use DateTime;

class AdSelectInventoryExporterCommand extends BaseCommand
{
    protected $signature = 'ops:adselect:inventory:export';

    protected $description = 'Export campaigns inventory to AdSelect';

    private $inventoryExporterService;

    /** @var NetworkCampaignRepository */
    private $campaignRepository;

    public function __construct(
        Locker $locker,
        AdSelectInventoryExporter $inventoryExporterService,
        NetworkCampaignRepository $campaignRepository
    ) {
        $this->inventoryExporterService = $inventoryExporterService;
        $this->campaignRepository = $campaignRepository;

        parent::__construct($locker);
    }

    public function handle()
    {
        if (!$this->lock(InventoryImporterCommand::getLockName())) {
            $this->info(
                'Supply inventory processing already running. Command '
                . $this->signature
                . ' cannot be started while import from demand or export to adselect is in progress'
            );

            return;
        }

        $this->info('Started exporting inventory to AdSelect.');

        $activeCampaigns = $this->campaignRepository->fetchActiveCampaigns();
        $deletedCampaigns = $this->campaignRepository->fetchCampaignsToDelete();

        $this->info(sprintf(
            'Found %s campaign to add or update, %s campaign to delete.',
            count($activeCampaigns),
            count($deletedCampaigns)
        ));

        $this->inventoryExporterService->export($activeCampaigns, $deletedCampaigns);

        Config::upsertDateTime(Config::ADSELECT_INVENTORY_EXPORT_TIME, new DateTime());

        $this->info('Finished exporting inventory to AdSelect.');
    }
}
