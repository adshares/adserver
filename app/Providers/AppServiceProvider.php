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

namespace Adshares\Adserver\Providers;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CliDriver;
use Adshares\Adserver\Services\Adpay;
use Adshares\Adserver\Services\Adselect;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->bind(
            Adselect::class,
            function ($app) {
                return new Adselect(config('app.adselect_endpoint'), config('app.debug'));
            }
        );
        $this->app->bind(
            AdsClient::class,
            function ($app) {
                $drv = new CliDriver(
                    config('app.adshares_address'),
                    config('app.adshares_secret'),
                    config('app.adshares_node_host'),
                    config('app.adshares_node_port')
                );
                $drv->setCommand(config('app.adshares_command'));
                $drv->setWorkingDir(config('app.adshares_workingdir'));

                return new AdsClient($drv);
            }
        );
    }
}
