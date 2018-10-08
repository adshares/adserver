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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/adshares/inventory/list', [\Adshares\Adserver\Http\Controllers\ApiController::class,'adsharesInventoryList']);
Route::get('/adshares/report/{tx_id}/{pay_to}', [\Adshares\Adserver\Http\Controllers\ApiController::class,'adsharesTransactionReport']);

Route::get('/click/{id}', [\Adshares\Adserver\Http\Controllers\DemandController::class,'click'])->name('banner-click');
Route::get('/serve/{id}', [\Adshares\Adserver\Http\Controllers\DemandController::class,'serve'])->name('banner-serve');
Route::get('/view/{id}', [\Adshares\Adserver\Http\Controllers\DemandController::class,'view'])->name('banner-view');
Route::get('/view.js', [\Adshares\Adserver\Http\Controllers\DemandController::class,'viewScript'])->name('demand-view.js');

Route::get('/l/context/{log_id}', [\Adshares\Adserver\Http\Controllers\DemandController::class,'logContext'])->name('log-context');
Route::get('/l/keywords/{log_id}', [\Adshares\Adserver\Http\Controllers\DemandController::class,'logKeywords'])->name('log-keywords');

Route::get('/supply/find', [\Adshares\Adserver\Http\Controllers\SupplyController::class,'find'])->name('supply-find');
Route::get('/supply/find.js', [\Adshares\Adserver\Http\Controllers\SupplyController::class,'findScript'])->name('supply-find.js');
Route::get('/l/n/view/{id}', [\Adshares\Adserver\Http\Controllers\SupplyController::class,'logNetworkView'])->name('log-network-view');
Route::get('/l/n/click/{id}', [\Adshares\Adserver\Http\Controllers\SupplyController::class,'logNetworkClick'])->name('log-network-click');
Route::get('/l/n/keywords/{log_id}', [\Adshares\Adserver\Http\Controllers\SupplyController::class,'logNetworkKeywords'])->name('log-network-keywords');
