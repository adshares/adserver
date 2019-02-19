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

use Adshares\Ads\AdsClient;
use Adshares\Adserver\Client\DummyAdClassifyClient;
use Adshares\Adserver\Client\GuzzleAdUserClient;
use Adshares\Adserver\Client\GuzzleDemandClient;
use Adshares\Adserver\Client\GuzzlePublisherClassifyClient;
use Adshares\Adserver\Client\JsonRpcAdPayClient;
use Adshares\Adserver\Client\JsonRpcAdSelectClient;
use Adshares\Adserver\Client\LocalPublisherClassifyClient;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Classify\Application\Service\ClassifierInterface;
use Adshares\Common\Application\Service\AdClassify;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Infrastructure\Service\PhpAdsClient;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\ClassifyClient;
use Adshares\Supply\Application\Service\DemandClient;
use GuzzleHttp\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ClientProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                return new JsonRpcAdPayClient(
                    new JsonRpc(
                        new Client(
                            [
                                'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                                'base_uri' => config('app.adpay_endpoint'),
                                'timeout' => 5,
                            ]
                        )
                    )
                );
            }
        );

        $this->app->bind(
            AdSelect::class,
            function () {
                return new JsonRpcAdSelectClient(
                    new JsonRpc(
                        new Client(
                            [
                                'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                                'base_uri' => config('app.adselect_endpoint'),
                                'timeout' => 4,
                            ]
                        )
                    )
                );
            }
        );

        $this->app->bind(
            AdUser::class,
            function () {
                return new GuzzleAdUserClient(
                    new Client(
                        [
                            'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                            'base_uri' => config('app.aduser_internal_location'),
                            'timeout' => 1,
                        ]
                    )
                );
            }
        );

        $this->app->bind(
            AdClassify::class,
            function () {
                return new DummyAdClassifyClient();
            }
        );

        $this->app->bind(
            DemandClient::class,
            function (Application $app) {
                return new GuzzleDemandClient($app->make(SignatureVerifier::class));
            }
        );

        $this->app->bind(
            Ads::class,
            function (Application $app) {
                return new PhpAdsClient($app->make(AdsClient::class));
            }
        );

        $this->app->bind(
            ClassifyClient::class,
            function (Application $app) {
                return new LocalPublisherClassifyClient($app->make(ClassifierInterface::class));
            }
        );
    }
}
