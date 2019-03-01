<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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


use Adshares\Adserver\Http\Controllers\DemandController;
use Adshares\Adserver\Http\Controllers\Manager\Simulator;
use Adshares\Adserver\Http\Controllers\Manager\CampaignsController;
use Adshares\Adserver\Http\Controllers\ClassifyController;
use Illuminate\Support\Facades\Route;

Route::get('/adshares/inventory/list', [DemandController::class, 'inventoryList'])->name('demand-inventory');

Route::get('/view.js', [DemandController::class, 'viewScript'])->name('demand-view.js');

Route::get('/serve/{id}', [DemandController::class, 'serve'])->name('banner-serve');
Route::get('/view/{id}', [DemandController::class, 'view'])->name('banner-view');
Route::get('/click/{id}', [DemandController::class, 'click'])->name('banner-click');
Route::get('/payment-details/{transactionId}/{accountAddress}/{date}/{signature}', [DemandController::class, 'paymentDetails']);

### simulator ###
Route::get('/get-data/{id}', [Simulator::class, 'userData']);

# should be moved to a better place - place for routing which don't have to be authenticated but belongs to manager
Route::get('/campaigns/banner/{id}/preview', [CampaignsController::class, 'preview'])->name('banner-preview');

Route::post('/classify/fetch', [ClassifyController::class, 'fetch']);
