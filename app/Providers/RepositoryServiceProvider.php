<?php

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Repository\Common\EloquentUserRepository;
use Adshares\Adserver\Repository\Common\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }
}
