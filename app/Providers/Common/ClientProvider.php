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

namespace Adshares\Adserver\Providers\Common;

use Adshares\Ads\AdsClient;
use Adshares\Adserver\Client\ClassifierExternalClient;
use Adshares\Adserver\Client\GuzzleAdPayClient;
use Adshares\Adserver\Client\GuzzleAdSelectClient;
use Adshares\Adserver\Client\GuzzleAdsOperatorClient;
use Adshares\Adserver\Client\GuzzleAdUserClient;
use Adshares\Adserver\Client\GuzzleClassifierExternalClient;
use Adshares\Adserver\Client\GuzzleDemandClient;
use Adshares\Adserver\Client\GuzzleLicenseClient;
use Adshares\Adserver\Client\GuzzleSupplyClient;
use Adshares\Adserver\Client\LocalPublisherBannerClassifier;
use Adshares\Adserver\Client\MultipleExternalClassifierAdClassifyClient;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Repository\Common\EloquentExchangeRateRepository;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use Adshares\Classify\Application\Service\ClassifierInterface;
use Adshares\Common\Application\Service\AdClassify;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Application\Service\LicenseProvider;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Infrastructure\Service\PhpAdsClient;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\BannerClassifier;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\SupplyClient;
use GuzzleHttp\Client;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

use function config;

final class ClientProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                return new GuzzleAdPayClient(
                    new Client(
                        [
                            'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                            'base_uri' => config('app.adpay_endpoint'),
                            'timeout' => 300,
                        ]
                    )
                );
            }
        );

        $this->app->bind(
            AdSelect::class,
            function () {
                $client = new Client(
                    [
                        'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                        'base_uri' => config('app.adselect_endpoint'),
                        'timeout' => 5,
                    ]
                );

                return new GuzzleAdSelectClient($client);
            }
        );

        $this->app->bind(
            AdUser::class,
            function () {
                return new GuzzleAdUserClient(new Client(
                    [
                        'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                        'base_uri' => config('app.aduser_base_url'),
                        'timeout' => 3,
                    ]
                ));
            }
        );

        $this->app->bind(
            AdClassify::class,
            function (Application $app) {
                return new MultipleExternalClassifierAdClassifyClient(
                    $app->make(ClassifierExternalClient::class),
                    new ClassifierExternalRepository()
                );
            }
        );

        $this->app->bind(
            DemandClient::class,
            function (Application $app) {
                $timeoutForDemandService = 15;

                return new GuzzleDemandClient(
                    $app->make(ClassifierExternalRepository::class),
                    $app->make(ClassifierExternalSignatureVerifier::class),
                    $app->make(SignatureVerifier::class),
                    $timeoutForDemandService
                );
            }
        );

        $this->app->bind(
            SupplyClient::class,
            function () {
                $timeoutForSupplyService = 15;

                return new GuzzleSupplyClient($timeoutForSupplyService);
            }
        );

        $this->app->bind(
            Ads::class,
            function (Application $app) {
                return new PhpAdsClient($app->make(AdsClient::class));
            }
        );

        $this->app->bind(
            BannerClassifier::class,
            function (Application $app) {
                return new LocalPublisherBannerClassifier($app->make(ClassifierInterface::class));
            }
        );

        $this->app->bind(
            LicenseProvider::class,
            function () {
                return new GuzzleLicenseClient(
                    new Client(
                        [
                            'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                            'base_uri' => config('app.license_url'),
                            'timeout' => 5,
                        ]
                    ),
                    (string)config('app.license_id')
                );
            }
        );

        $this->app->bind(
            ExchangeRateRepository::class,
            function () {
                return new GuzzleAdsOperatorClient(
                    new Client(
                        [
                            'headers' => ['Content-Type' => 'application/json'],
                            'base_uri' => config('app.ads_operator_server_url'),
                            'timeout' => 5,
                        ]
                    )
                );
            }
        );

        $this->app->bind(
            EloquentExchangeRateRepository::class,
            function () {
                return new EloquentExchangeRateRepository();
            }
        );

        $this->app->bind(
            ClassifierExternalClient::class,
            function () {
                return new GuzzleClassifierExternalClient(
                    new Client(
                        [
                            'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
                            'timeout' => 30,
                        ]
                    )
                );
            }
        );
    }
}
