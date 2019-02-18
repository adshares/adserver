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

namespace Adshares\Adserver\Providers\Supply;

use Adshares\Adserver\Manager\EloquentTransactionManager;
use Adshares\Adserver\Repository\Supply\NetworkCampaignRepository;
use Adshares\Supply\Application\Service\ClassifierClient;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\InventoryImporter;
use Adshares\Supply\Application\Service\MarkedCampaignsAsDeleted;
use Adshares\Supply\Infrastructure\Service\SodiumCompatClassifyVerifier;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class InventoryImporterProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            InventoryImporter::class,
            function (Application $app) {
                $campaignRepository = new NetworkCampaignRepository();
                $markedCampaignsAsDeactivatedService = new MarkedCampaignsAsDeleted($campaignRepository);
                $classifyPublicKey = config('app.classify_publisher_public_key');

                return new InventoryImporter(
                    $markedCampaignsAsDeactivatedService,
                    $campaignRepository,
                    $app->make(DemandClient::class),
                    $app->make(ClassifierClient::class),
                    new SodiumCompatClassifyVerifier($classifyPublicKey),
                    new EloquentTransactionManager()
                );
            }
        );
    }
}
