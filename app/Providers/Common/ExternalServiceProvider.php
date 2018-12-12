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
use Adshares\Adserver\Client\GuzzleAdUserClient;
use Adshares\Adserver\Client\JsonRpcAdPayClient;
use Adshares\Adserver\Client\JsonRpcAdSelectClient;
use Adshares\Adserver\HttpClient\AdPayHttpClient;
use Adshares\Adserver\HttpClient\AdSelectHttpClient;
use Adshares\Adserver\HttpClient\AdUserHttpClient;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Common\Application\Service\FilteringOptionsSource;
use Adshares\Common\Application\Service\TargetingOptionsSource;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Service\BannerFinder;
use Adshares\Supply\Application\Service\InventoryExporter;
use Adshares\Supply\Application\Service\UserContextProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ExternalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdPay::class, function (Application $app) {
            return new JsonRpcAdPayClient(new JsonRpc($app->make(AdPayHttpClient::class)));
        });

        $this->app->bind(BannerFinder::class, function (Application $app) {
            return new JsonRpcAdSelectClient(new JsonRpc($app->make(AdSelectHttpClient::class)));
        });

        $this->app->bind(TargetingOptionsSource::class, function (Application $app) {
            return $app->make(AdUserHttpClient::class);
        });

        $this->app->bind(FilteringOptionsSource::class, function () {
            return new DummyAdClassifyClient();
        });

        $this->app->bind(UserContextProvider::class, function (Application $app) {
            return new GuzzleAdUserClient($app->make(AdUserHttpClient::class));
        });

        $this->app->bind(InventoryExporter::class, function (Application $app) {
            return new JsonRpcAdSelectClient(new JsonRpc($app->make(AdSelectHttpClient::class)));
        });
    }
}
