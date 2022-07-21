<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests;

use Adshares\Ads\AdsClient;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\AdsRpcClient;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Mock\Client\DummyAdsClient;
use Adshares\Mock\Client\DummyAdsRpcClient;
use Adshares\Mock\Client\DummyAdUserClient;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use Adshares\Supply\Application\Service\DemandClient;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    private const DISK = 'banners';

    protected Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake(self::DISK);
        $this->faker = Factory::create();

        Config::updateAdminSettings(
            [
                Config::ADSHARES_ADDRESS => '0001-00000005-CBCA',
            ]
        );

        $adsClient = $this->app->make(AdsClient::class);

        $this->app->bind(
            ExchangeRateRepository::class,
            static function () {
                return new DummyExchangeRateRepository();
            }
        );
        $this->app->bind(
            Ads::class,
            static function () use ($adsClient) {
                return new DummyAdsClient($adsClient);
            }
        );
        $this->app->bind(
            AdsRpcClient::class,
            static function () {
                return new DummyAdsRpcClient();
            }
        );
        $this->app->bind(
            AdUser::class,
            static function () {
                return new DummyAdUserClient();
            }
        );
        $this->app->bind(
            DemandClient::class,
            static function () {
                return new DummyDemandClient();
            }
        );
    }

    protected function login(User $user = null): User
    {
        if (null === $user) {
            $user = User::factory()->create();
        }
        $this->actingAs($user, 'api');
        return $user;
    }
}
