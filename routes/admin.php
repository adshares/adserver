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

use Adshares\Adserver\Http\Controllers\Manager\AdminController;
use Adshares\Adserver\Http\Controllers\Manager\AuthController;
use Adshares\Adserver\Http\Controllers\Manager\BidStrategyController;
use Adshares\Adserver\Http\Controllers\Manager\UsersController;
use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;

Route::middleware([Kernel::ADMIN_ACCESS, Kernel::JSON_API])->group(function () {
    Route::get('settings', [AdminController::class, 'listSettings']);
    Route::put('settings', [AdminController::class, 'updateSettings']);

    Route::get('wallet', [AdminController::class, 'wallet']);
    Route::get('license', [AdminController::class, 'getLicense']);

    Route::get('terms', [AdminController::class, 'getTerms']);
    Route::put('terms', [AdminController::class, 'putTerms']);
    Route::get('privacy', [AdminController::class, 'getPrivacyPolicy']);
    Route::put('privacy', [AdminController::class, 'putPrivacyPolicy']);

    Route::patch('panel-placeholders', [AdminController::class, 'patchPanelPlaceholders']);
    Route::get('index/update-time', [AdminController::class, 'getIndexUpdateTime']);

    Route::get('impersonation/{user}', [AuthController::class, 'impersonate']);
    Route::post('users/{id}/confirm', [AuthController::class, 'confirm']);

    Route::get('users', [UsersController::class, 'browse']);
    Route::get('advertisers', [UsersController::class, 'advertisers']);
    Route::get('publishers', [UsersController::class, 'publishers']);

    Route::put('campaigns/bid-strategy/uuid-default', [BidStrategyController::class, 'putBidStrategyUuidDefault']);

    Route::get('rejected-domains', [AdminController::class, 'getRejectedDomains']);
    Route::put('rejected-domains', [AdminController::class, 'putRejectedDomains']);
});
