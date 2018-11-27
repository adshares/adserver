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
use Adshares\Adserver\HttpClient\AdUserHttpClient;
use Adshares\Adserver\Repository\DummyConfigurationRepository;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Application\Service\FilteringOptionsSource;
use Adshares\Common\Application\Service\TargetingOptionsSource;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class OptionsProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TargetingOptionsSource::class, function (Application $app) {
            return new GuzzleAdUserClient($app->make(AdUserHttpClient::class));
        });

        $this->app->bind(FilteringOptionsSource::class, function () {
            return new DummyAdClassifyClient();
        });

        $this->app->bind(ConfigurationRepository::class, function (Application $app) {
            return new DummyConfigurationRepository(
                $app->make(TargetingOptionsSource::class),
                $app->make(FilteringOptionsSource::class)
            );
        });

    }

}
