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

namespace Adshares\Adserver\Providers\Supply;

use Adshares\Ads\AdsClient;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Infrastructure\Service\PhpAdsClient;
use Adshares\Common\Infrastructure\Service\SodiumCompatSignatureVerifier;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;

class PaymentDetailsVerifyProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SignatureVerifier::class,
            function () {
                return new SodiumCompatSignatureVerifier();
            }
        );

        $this->app->bind(
            Ads::class,
            function (Application $app) {
                return new PhpAdsClient($app->make(AdsClient::class));
            }
        );


        $this->app->bind(PaymentDetailsVerify::class, function (Application $app) {
            return new PaymentDetailsVerify(
                $app->make(SignatureVerifier::class),
                $app->make(Ads::class)
            );
        });
    }
}
