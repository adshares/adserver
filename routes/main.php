<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

declare(strict_types = 1);

use Adshares\Adserver\Http\Controllers\InfoController;
use Adshares\Adserver\Http\Controllers\Manager\CampaignsController;
use Adshares\Adserver\Http\Controllers\Manager\InvoicesController;
use Adshares\Adserver\Http\Controllers\Manager\SettingsController;
use Adshares\Adserver\Http\Controllers\Manager\StatisticsGlobalController;
use Adshares\Adserver\Http\Controllers\Manager\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InfoController::class, 'info'])
    ->name('login');
Route::get('/info', [InfoController::class, 'info']);
Route::get('/info.json', [InfoController::class, 'info'])
    ->name('app.infoEndpoint');
Route::get('/upload-preview/{type}/{name}', [CampaignsController::class, 'uploadPreview'])
    ->name('app.campaigns.upload_preview');

Route::get('/stats/demand/statistics', [StatisticsGlobalController::class, 'fetchDemandStatistics']);
Route::get('/stats/demand/domains', [StatisticsGlobalController::class, 'fetchDemandDomains']);
Route::get('/stats/demand/campaigns', [StatisticsGlobalController::class, 'fetchDemandCampaigns']);
Route::get('/stats/demand/banners/sizes', [StatisticsGlobalController::class, 'fetchDemandBannersSizes']);

Route::get('/stats/supply/statistics', [StatisticsGlobalController::class, 'fetchSupplyStatistics']);
Route::get('/stats/supply/domains', [StatisticsGlobalController::class, 'fetchSupplyDomains']);
Route::get('/stats/supply/zones/sizes', [StatisticsGlobalController::class, 'fetchSupplyZonesSizes']);

Route::get('/stats/server/{date}', [StatisticsGlobalController::class, 'fetchServerStatisticsAsFile']);

Route::get('/policies/privacy.html', [InfoController::class, 'privacyPolicy']);
Route::get('/policies/terms.html', [InfoController::class, 'terms']);
Route::get('/panel/placeholders', [InfoController::class, 'getPanelPlaceholders']);

Route::get('/newsletter/unsubscribe', [SettingsController::class, 'newsletterUnsubscribe'])->name('newsletter-unsubscribe');

Route::post('/now-payments/notify/{uuid}', [WalletController::class, 'nowPaymentsNotify'])->name('now-payments.notify');
Route::post('/now-payments/exchange/{uuid}', [WalletController::class, 'nowPaymentsExchange'])->name('now-payments.exchange');

Route::post('/withdraw/exchange', [WalletController::class, 'withdrawExchange'])->name('withdraw.exchange');
Route::get('/invoices/{invoice_uuid}.pdf', [InvoicesController::class, 'download'])->name('invoices.download');
