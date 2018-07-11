<?php

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Services\Adpay;
use Adshares\Adserver\Services\Adselect;
use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CliDriver;
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
        $this->app->bind(Adpay::class, function ($app) {
            return new Adpay(config('app.adpay_endpoint'), config('app.debug'));
        });
        $this->app->bind(Adselect::class, function ($app) {
            return new Adselect(config('app.adselect_endpoint'), config('app.debug'));
        });
        $this->app->bind(AdsClient::class, function ($app) {
            $drv = new CliDriver(
                config('app.adshares_address'),
                config('app.adshares_secret'),
                config('app.adshares_node_host'),
                config('app.adshares_node_port')
            );
            $drv->setCommand(config('app.adshares_command'));
            $drv->setWorkingDir(config('app.adshares_workingdir'));

            return new AdsClient($drv);
        });
    }
}
