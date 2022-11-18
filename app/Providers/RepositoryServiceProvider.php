<?php

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Repository\Advertiser\CampaignRepository;
use Adshares\Adserver\Repository\Advertiser\EloquentCampaignRepository;
use Adshares\Adserver\Repository\Common\EloquentServerEventLogRepository;
use Adshares\Adserver\Repository\Common\EloquentUserRepository;
use Adshares\Adserver\Repository\Common\ServerEventLogRepository;
use Adshares\Adserver\Repository\Common\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepository::class, EloquentCampaignRepository::class);
        $this->app->bind(ServerEventLogRepository::class, EloquentServerEventLogRepository::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }
}
