<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Http\Controllers\CampaignOptionsController;
use Adshares\Adserver\Http\Controllers\Rest\ChartsController;
use Adshares\Adserver\Http\Controllers\Rest\ConfigController;
use Adshares\Adserver\Http\Controllers\Rest\NotificationsController;
use Adshares\Adserver\Http\Controllers\Rest\SettingsController;
use Adshares\Adserver\Http\Controllers\Rpc\WalletController;
use Adshares\Adserver\Http\Controllers\Simulator;
use Adshares\Adserver\Http\Controllers\SiteOptionsController;
use Illuminate\Support\Facades\Route;

Route::get('config/adshares-address', [ConfigController::class, 'adsharesAddress']);
Route::get('notifications', [NotificationsController::class, 'read']);
Route::get('settings/notifications', [SettingsController::class, 'readNotifications']);

Route::get('options/campaigns/targeting', [CampaignOptionsController::class, 'targeting']);

Route::get('options/sites/filtering', [SiteOptionsController::class, 'filtering']);
Route::get('options/sites/languages', [SiteOptionsController::class, 'languages']);
Route::get('options/sites/zones', [SiteOptionsController::class, 'zones']);

// Routes for Withdraw/Deposit
Route::post('calculate-withdrawal', [WalletController::class, 'calculateWithdrawal']);
Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);
Route::get('deposit-info', [WalletController::class, 'depositInfo']);
Route::get('wallet/history', [WalletController::class, 'history']);

// tmp mocked solutions
Route::post('chart', [ChartsController::class, 'chart']);
Route::post('publisher_chart', [ChartsController::class, 'publisherChart']);
Route::get('admin/settings', [Simulator::class, 'mock']);
Route::get('account/history', [Simulator::class, 'mock']);


