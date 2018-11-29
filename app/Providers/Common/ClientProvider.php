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

namespace Adshares\Adserver\Providers\Common;

use Adshares\Adserver\Client\DummyAdClassifyClient;
use Adshares\Adserver\Client\DummyAdUserClient;
use Adshares\Adserver\Client\GuzzleAdSelectClient;
use Adshares\Adserver\Client\JsonRpcAdSelectClient;
use Adshares\Adserver\HttpClient\AdSelectHttpClient;
use Adshares\Adserver\HttpClient\AdUserHttpClient;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Common\Application\Service\FilteringOptionsSource;
use Adshares\Common\Application\Service\TargetingOptionsSource;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use Adshares\Supply\Application\Service\BannerFinder;
use Adshares\Supply\Application\Service\ImpressionContextProvider;
use GuzzleHttp\Client;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ClientProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdSelectHttpClient::class, function () {
            return new Client([
                'headers' => ['Content-Type' => 'application/json'],
                'base_uri' => config('app.adselect_endpoint'),
                'timeout' => 5.0,
            ]);
        });

        $this->app->bind(AdUserHttpClient::class, function () {
            return new Client([
                'headers' => ['Content-Type' => 'application/json'],
                'base_uri' => config('app.aduser_internal_location'),
                'timeout' => 5.0,
            ]);
        });

        $this->app->bind(BannerFinder::class, function (Application $app) {
            return new JsonRpcAdSelectClient(
                new JsonRpc(
                    $app->make(AdSelectHttpClient::class)
                )
            );
        });

        $this->app->bind(TargetingOptionsSource::class, function (Application $app) {
            return $app->make(AdUserHttpClient::class);
        });

        $this->app->bind(FilteringOptionsSource::class, function () {
            return new DummyAdClassifyClient();
        });

        $this->app->bind(AdSelectInventoryExporter::class, function (Application $app) {
            return new AdSelectInventoryExporter(
                new JsonRpcAdSelectClient(new JsonRpc($app->make(AdSelectHttpClient::class)))
            );
        });

        $this->app->bind(ImpressionContextProvider::class, function () {
            return new DummyAdUserClient();
        });
    }
}
