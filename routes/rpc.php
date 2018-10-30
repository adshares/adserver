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

use Adshares\Adserver\Http\Controllers\Rest\CampaignsController;
use Adshares\Adserver\Http\Controllers\Rest\ChartsController;
use Adshares\Adserver\Http\Controllers\Rest\ConfigController;
use Adshares\Adserver\Http\Controllers\Rest\NotificationsController;
use Adshares\Adserver\Http\Controllers\Rest\SettingsController;
use Adshares\Adserver\Http\Controllers\Rpc\WalletController;
use Adshares\Adserver\Http\Controllers\Simulator;
use Illuminate\Support\Facades\Route;

Route::get('config/adshares-address', [ConfigController::class, 'adsharesAddress']);
Route::get('notifications', [NotificationsController::class, 'read']);
Route::get('settings/notifications', [SettingsController::class, 'readNotifications']);

// tmp mocked solutions
Route::post('chart', [ChartsController::class, 'chart']);
Route::get('options/campaigns/targeting', [CampaignsController::class, 'targeting']);
Route::get('options/sites/targeting', [Simulator::class, 'targeting']);
Route::post('publisher_chart', [ChartsController::class, 'publisherChart']);
Route::get('config/banners', [Simulator::class, 'zoneTypes']);//@deprecated
Route::get('options/sites/zones', [Simulator::class, 'zoneTypes']);

// Routes for Withdraw/Deposit
Route::post('calculate-withdrawal', [WalletController::class, 'calculateWithdrawal']);
//{"amount":10,"to":"ADS-ADDR"}//TODO add max option - for calculate maximum available amount to withdraw
//200{"amount":10,"fee":1,"total":11}
Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);
//{"amount":10,"to":"ADS-ADDR"}
//204
Route::get('deposit-info', [WalletController::class, 'depositInfo']);
//{}
//200{"address":"ADS_ADDR","title":"000...uid"}
Route::get('wallet/history', [WalletController::class, 'history']);
//{}
//200[
//    {
//        "status": "0.05002500000",
//        "date": "Wed, 24 Oct 2018 15:56:49 GMT",
//        "address": "0001-00000005-CBCA",
//        "link": "https://operator1.e11.click/blockexplorer/transactions/0001:0000000A:0001"
//    },
//    {
//        "status": "-0.00000010010",
//        "date": "Wed, 24 Oct 2018 16:14:58 GMT",
//        "address": "0001-00000005-CBCA",
//        "link": "-"
//    }
//]

Route::get('admin/settings', [Simulator::class, 'mock']);
Route::get('account/history', [Simulator::class, 'mock']);


