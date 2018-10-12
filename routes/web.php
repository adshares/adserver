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

use Adshares\Adserver\Http\Controllers\ApiController;
use Adshares\Adserver\Http\Controllers\DemandController;
use Adshares\Adserver\Http\Controllers\Simulator;
use Adshares\Adserver\Http\Controllers\SupplyController;

Route::get('/', function () {
    return "";
})->name('login');

Route::get('/adshares/inventory/list', [ApiController::class, 'adsharesInventoryList']);
Route::get('/adshares/report/{tx_id}/{pay_to}', [ApiController::class, 'adsharesTransactionReport']);

Route::get('/click/{id}', [DemandController::class, 'click'])->name('banner-click');
Route::get('/serve/{id}', [DemandController::class, 'serve'])->name('banner-serve');
Route::get('/view/{id}', [DemandController::class, 'view'])->name('banner-view');
Route::get('/view.js', [DemandController::class, 'viewScript'])->name('demand-view.js');

Route::get('/l/context/{log_id}', [DemandController::class, 'logContext'])->name('log-context');
Route::get('/l/keywords/{log_id}', [DemandController::class, 'logKeywords'])->name('log-keywords');

Route::get('/supply/find', [SupplyController::class, 'find'])->name('supply-find');
Route::get('/supply/find.js', [SupplyController::class, 'findScript'])->name('supply-find.js');

Route::get('/l/n/view/{id}', [SupplyController::class, 'logNetworkView'])->name('log-network-view');
Route::get('/l/n/click/{id}', [SupplyController::class, 'logNetworkClick'])->name('log-network-click');
Route::get('/l/n/keywords/{log_id}', [SupplyController::class, 'logNetworkKeywords'])->name('log-network-keywords');

### something is no-yes in find.js ###
Route::get('/l/n/click', [Simulator::class, 'pixel']);
Route::get('/l/n/view', [Simulator::class, 'pixel']);

### simulator ###
Route::get('/pixel/{id}', [Simulator::class, 'pixel']);
Route::get('/get-data/{id}', [Simulator::class, 'userData']);
