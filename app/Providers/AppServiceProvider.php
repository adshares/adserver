<?php

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Services\Adselect;
use Adshares\Esc\Esc;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(Adselect::class, function ($app) {
            return new Adselect(config('app.adselect_endpoint'), config('app.debug'));
        });
        $this->app->bind(Esc::class, function ($app) {
            // Esc($walletCommand, $workingDir, $address, $secret, $host, $port)
            return new Esc(
                config('app.adshares_wallet'),
                config('app.adshares_workdir'),
                config('app.adshares_address'),
                config('app.adshares_secret'),
                config('app.adshares_node_host'),
                config('app.adshares_node_port')
            );
        });
    }
}
