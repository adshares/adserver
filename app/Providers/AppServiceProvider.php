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
use Adshares\Common\Application\Service\LicenseDecoder;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Infrastructure\Service\LicenseDecoderV1;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Common\Infrastructure\Service\LicenseVaultFilesystem;
use Adshares\Demand\Application\Service\TransferMoneyToColdWallet;
use Adshares\Demand\Application\Service\WalletFundsChecker;
use Adshares\Publisher\Repository\StatsRepository as PublisherStatsRepository;
use Adshares\Advertiser\Repository\StatsRepository as AdvertiserStatsRepository;
use Adshares\Adserver\Repository\Advertiser\MySqlStatsRepository as MysqlAdvertiserStatsRepository;
use Adshares\Adserver\Repository\Publisher\MySqlStatsRepository as MysqlPublisherStatsRepository;
use Adshares\Adserver\Services\Adselect;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Storage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            Adselect::class,
            function () {
                return new Adselect(config('app.adselect_endpoint'), config('app.debug'));
            }
        );

        $this->app->bind(
            AdsClient::class,
            function () {
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

        $this->app->bind(
            AdvertiserStatsRepository::class,
            function () {
                return new MysqlAdvertiserStatsRepository();
            }
        );

        $this->app->bind(
            PublisherStatsRepository::class,
            function () {
                return new MysqlPublisherStatsRepository();
            }
        );

        $this->app->bind(
            TransferMoneyToColdWallet::class,
            function (Application $app) {
                $coldWalletAddress = (string)config('app.adshares_wallet_cold_address');
                $minAmount = (int)config('app.adshares_wallet_min_amount');
                $maxAmount = (int)config('app.adshares_wallet_max_amount');
                $adsClient = $app->make(AdsClient::class);

                return new TransferMoneyToColdWallet($minAmount, $maxAmount, $coldWalletAddress, $adsClient);
            }
        );

        $this->app->bind(
            WalletFundsChecker::class,
            function (Application $app) {
                $minAmount = (int)config('app.adshares_wallet_min_amount');
                $maxAmount = (int)config('app.adshares_wallet_max_amount');
                $adsClient = $app->make(AdsClient::class);

                return new WalletFundsChecker($minAmount, $maxAmount, $adsClient);
            }
        );

        $this->app->bind(
            LicenseDecoder::class,
            function () {
                return new LicenseDecoderV1((string)config('app.license_key'));
            }
        );

        $this->app->bind(
            LicenseVault::class,
            function (Application $app) {
                $path = Storage::disk('local')->path('license.txt');
                return new LicenseVaultFilesystem($path, $app->make(LicenseDecoder::class));
            }
        );

        $this->app->bind(
            LicenseReader::class,
            function (Application $app) {
                return new LicenseReader($app->make(LicenseVault::class));
            }
        );
    }
}
