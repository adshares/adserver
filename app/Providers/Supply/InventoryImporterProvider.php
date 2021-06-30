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

namespace Adshares\Adserver\Providers\Supply;

use Adshares\Adserver\Manager\EloquentTransactionManager;
use Adshares\Adserver\Repository\Supply\NetworkCampaignRepository;
use Adshares\Supply\Application\Service\BannerClassifier;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\InventoryImporter;
use Adshares\Supply\Application\Service\MarkedCampaignsAsDeleted;
use Adshares\Supply\Domain\Repository\CampaignRepository;
use Adshares\Supply\Infrastructure\Service\SodiumCompatClassifyVerifier;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class InventoryImporterProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CampaignRepository::class,
            function () {
                return new NetworkCampaignRepository();
            }
        );

        $this->app->bind(
            InventoryImporter::class,
            function (Application $app) {
                return new InventoryImporter(
                    new MarkedCampaignsAsDeleted($app->make(CampaignRepository::class)),
                    $app->make(CampaignRepository::class),
                    $app->make(DemandClient::class),
                    $app->make(BannerClassifier::class),
                    new EloquentTransactionManager()
                );
            }
        );
    }
}
