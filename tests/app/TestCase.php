<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Service\AdClassify;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\AdsRpcClient;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Mock\Client\DummyAdClassifyClient;
use Adshares\Mock\Client\DummyAdPayClient;
use Adshares\Mock\Client\DummyAdsClient;
use Adshares\Mock\Client\DummyAdSelectClient;
use Adshares\Mock\Client\DummyAdsRpcClient;
use Adshares\Mock\Client\DummyAdUserClient;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use Adshares\Mock\Client\DummySupplyClient;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\SupplyClient;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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
        Event::fake()->except([
            'eloquent.creating: Laravel\Passport\Client',
            'eloquent.creating: Adshares\Adserver\Models\UploadedFile',
        ]);
        Mail::fake();
        Queue::fake();
        Storage::fake(self::DISK);
        $this->faker = Factory::create();

        Config::updateAdminSettings(
            [
                Config::ADPANEL_URL => 'http://adpanel',
                Config::ADSHARES_ADDRESS => '0001-00000005-CBCA',
                Config::ADSHARES_LICENSE_SERVER_URL => 'http://license-server',
                Config::ADSHARES_SECRET => 'CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB',
                Config::CLASSIFIER_EXTERNAL_API_KEY_NAME => 'api_key_name',
                Config::CLASSIFIER_EXTERNAL_API_KEY_SECRET => 'api_key_secret',
                Config::CLASSIFIER_EXTERNAL_BASE_URL => 'http://classifier',
                Config::CLASSIFIER_EXTERNAL_NAME => 'test_classifier',
                Config::CLASSIFIER_EXTERNAL_PUBLIC_KEY =>
                    'D69BCCF69C2D0F6CED025A05FA7F3BA687D1603AC1C8D9752209AC2BBF2C4D17',
            ]
        );
        DatabaseConfigReader::overwriteAdministrationConfig();

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
            AdClassify::class,
            static function () {
                return new DummyAdClassifyClient();
            }
        );
        $this->app->bind(
            AdPay::class,
            static function () {
                return new DummyAdPayClient();
            }
        );
        $this->app->bind(
            AdsRpcClient::class,
            static function () {
                return new DummyAdsRpcClient();
            }
        );
        $this->app->bind(
            AdSelect::class,
            function () {
                return new DummyAdSelectClient();
            }
        );
        $this->app->bind(
            AdUser::class,
            static function () {
                return new DummyAdUserClient();
            }
        );
        $this->app->bind(
            ConfigurationRepository::class,
            static function () {
                return new DummyConfigurationRepository();
            }
        );
        $this->app->bind(
            DemandClient::class,
            static function () {
                return new DummyDemandClient();
            }
        );
        $this->app->bind(
            LicenseReader::class,
            function () {
                $licenseReader = self::createMock(LicenseReader::class);
                $licenseReader->method('getAddress')->willReturn(new AccountId('FFFF-00000000-3F2E'));
                $licenseReader->method('getFee')->willReturn(0.01);
                return $licenseReader;
            }
        );
        $this->app->bind(SupplyClient::class, fn() => new DummySupplyClient());
    }

    protected function login(User $user = null): User
    {
        if (null === $user) {
            $user = User::factory()->create();
        }
        $this->actingAs($user, 'api');
        return $user;
    }

    protected static function assertServerEventDispatched(ServerEventType $type, array $properties = null): void
    {
        Event::assertDispatched(
            fn (ServerEvent $event) =>
                $type === $event->getType() &&
                (null === $properties || array_intersect_assoc($event->getProperties(), $properties) === $properties)
        );
    }
}
