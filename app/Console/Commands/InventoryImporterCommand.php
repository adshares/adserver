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

declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\InventoryImporter;
use Illuminate\Console\Command;

class InventoryImporterCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:demand:inventory:import';

    protected $description = 'Import data from all defined inventories';

    private $inventoryImporterService;

    private $networkHost;

    public function __construct(InventoryImporter $inventoryImporterService)
    {
        $this->inventoryImporterService = $inventoryImporterService;

        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        $networkHosts = NetworkHost::fetchHosts();

        if ($networkHosts->count() === 0) {
            $this->info('[Inventory Importer] Stopped importing. No hosts found.');

            return;
        }

        try {
            /** @var NetworkHost $networkHost */
            foreach ($networkHosts as $networkHost) {
                $this->inventoryImporterService->import($networkHost->info->getInventoryUrl());

                $networkHost->connectionSuccessful();
            }
        } catch (UnexpectedClientResponseException $exception) {
            $networkHost->connectionFailed();
            $this->error(sprintf('[Inventory Importer] %s', $exception->getMessage()));
            return;
        }

        $this->info('[Inventory Importer] Finished importing data from all inventories.');
    }
}
