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
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Application\Service\InventoryImporter;

class InventoryImporterCommand extends BaseCommand
{
    protected $signature = 'ops:demand:inventory:import';

    protected $description = 'Import data from all defined inventories';

    /** @var InventoryImporter  */
    private $inventoryImporterService;

    public function __construct(Locker $locker, InventoryImporter $inventoryImporterService)
    {
        $this->inventoryImporterService = $inventoryImporterService;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock(self::getLockName())) {
            $this->info(
                'Supply inventory processing already running. Command '
                . $this->signature
                . ' cannot be started while import from demand or export to adselect is in progress'
            );

            return;
        }

        $this->info('Start command ' . $this->signature);

        $this->removeNonExistentHosts();

        $networkHosts = NetworkHost::fetchHosts();

        $networkHostCount = $networkHosts->count();
        if ($networkHostCount === 0) {
            $this->info('[Inventory Importer] Stopped importing. No hosts found.');

            return;
        }

        $networkHostSuccessfulConnectionCount = 0;
        /** @var NetworkHost $networkHost */
        foreach ($networkHosts as $networkHost) {
            $address = new AccountId($networkHost->address);
            $info = $networkHost->info;
            try {
                $this->inventoryImporterService->import($address, $info->getServerUrl(), $info->getInventoryUrl());

                $networkHost->connectionSuccessful();
                ++$networkHostSuccessfulConnectionCount;
            } catch (UnexpectedClientResponseException $exception) {
                $networkHost->connectionFailed();

                $this->warn(sprintf(
                    '[Inventory Importer] Inventory (%s) is unavailable (Exception: %s)',
                    $address->toString(),
                    $exception->getMessage()
                ));

                if ($networkHost->isInventoryToBeRemoved()) {
                    $this->inventoryImporterService->clearInventoryForHostAddress($address);

                    $this->info(sprintf(
                        '[Inventory Importer] Inventory (%s) has been removed.',
                        $address->toString()
                    ));
                }
            } catch (EmptyInventoryException $exception) {
                $this->inventoryImporterService->clearInventoryForHostAddress($address);

                $this->info(sprintf(
                    '[Inventory Importer] Inventory (%s) is empty. It has been removed from the database.',
                    $address->toString()
                ));
            }
        }

        $this->info(
            sprintf(
                '[Inventory Importer] Finished importing data from %d/%d inventories.',
                $networkHostSuccessfulConnectionCount,
                $networkHostCount
            )
        );
    }

    private function removeNonExistentHosts(): void
    {
        $addresses = NetworkHost::findNonExistentHostsAddresses();

        foreach ($addresses as $address) {
            $this->inventoryImporterService->clearInventoryForHostAddress(new AccountId($address));

            $this->info(
                sprintf(
                    '[Inventory Importer] Non existent inventory (%s) has been removed.',
                    $address
                )
            );
        }
    }

    public static function getLockName(): string
    {
        return config('app.adserver_id') . 'SupplyInventoryProcessing';
    }
}
