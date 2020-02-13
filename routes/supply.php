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

declare(strict_types = 1);

use Adshares\Adserver\Http\Controllers\Manager\Simulator;
use Adshares\Adserver\Http\Controllers\SupplyController;
use Illuminate\Support\Facades\Route;

Route::get('/supply/find', [SupplyController::class, 'find']);
Route::get('/supply/find/{data}', [SupplyController::class, 'find']);
Route::post('/supply/find', [SupplyController::class, 'find']);

Route::get('/main.js', [SupplyController::class, 'findScript']);
Route::get('/supply/find.js', [SupplyController::class, 'findScript']);
Route::get('/supply/register', [SupplyController::class, 'register']);

Route::get('/l/n/view/{id}', [SupplyController::class, 'logNetworkView']);
Route::get('/l/n/click/{id}', [SupplyController::class, 'logNetworkClick']);

Route::get('/supply/targeting-reach', [SupplyController::class, 'targetingReachList']);

### simulator ###
Route::get('/get-data/{id}', [Simulator::class, 'userData']);

# WHY PAGE
Route::get('/supply/why', [SupplyController::class, 'why']);
Route::get('/supply/ad/report/{case_id}/{banner_id}', [SupplyController::class, 'reportAd'])
    ->name('report-ad');

Route::group(
    ['domain' => config('app.serve_base_url')],
    function () {
        Route::get('/l/n/view/{id}', [SupplyController::class, 'logNetworkView'])
            ->name('log-network-view');
        Route::get('/l/n/click/{id}', [SupplyController::class, 'logNetworkClick'])
            ->name('log-network-click');
    }
);

Route::group(
    ['domain' => config('app.main_js_base_url')],
    function () {
        Route::get('/main.js', [SupplyController::class, 'findScript'])
            ->name('supply-find.js');
    }
);
