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

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Client\DummyAdClassifyClient;
use Adshares\Adserver\Client\GuzzleAdUserClient;
use Adshares\Adserver\Client\JsonRpcAdSelectClient;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\Repository\DummyConfigurationRepository;
use Adshares\Common\Application\Service\AdClassifyClient;
use Adshares\Common\Application\Service\AdUserClient;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Supply\Application\Service\BannerFinderClient;
use GuzzleHttp\Client;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class TaxonomyImporterProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdUserClient::class, function () {
            return new GuzzleAdUserClient(new Client([
                'base_uri' => config('app.aduser_internal_location'),
                'timeout' => 5.0,
            ]));
        });
        $this->app->bind(AdClassifyClient::class, function () {
            return new DummyAdClassifyClient();
        });

        $this->app->bind(ConfigurationRepository::class, function (Application $app) {
            return new DummyConfigurationRepository(
                $app->make(AdUserClient::class),
                $app->make(AdClassifyClient::class)
            );
        });

        $this->app->bind(BannerFinderClient::class, function () {
            $client = new Client([
                'headers' => ['Content-Type' => 'application/json'],
                'base_uri' => config('app.adselect_endpoint'),
                'timeout' => 5.0,
            ]);

            return new JsonRpcAdSelectClient(new JsonRpc($client));
        });
    }
}
