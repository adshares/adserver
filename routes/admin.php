<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

declare(strict_types=1);

use Adshares\Adserver\Http\Controllers\Manager\AdminController;
use Adshares\Adserver\Http\Controllers\Manager\AuthController;
use Adshares\Adserver\Http\Controllers\Manager\BidStrategyController;
use Adshares\Adserver\Http\Controllers\Manager\UsersController;
use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;

Route::middleware([Kernel::ADMIN_ACCESS, Kernel::JSON_API])->group(function () {
    Route::get('settings', [AdminController::class, 'getSettings']);
    Route::get('license', [AdminController::class, 'getLicense']);

    Route::get('index/update-time', [AdminController::class, 'getIndexUpdateTime']);
    Route::patch(
        'campaigns/bid-strategy/media/{medium}/uuid-default',
        [BidStrategyController::class, 'patchBidStrategyUuidDefault']
    );

    Route::post('users/{id}/switchToModerator', [AdminController::class, 'switchUserToModerator']);
    Route::post('users/{id}/ban', [AdminController::class, 'banUser']);
    Route::post('users/{id}/unban', [AdminController::class, 'unbanUser']);
    Route::post('users/{id}/delete', [AdminController::class, 'deleteUser']);
});

Route::middleware([Kernel::MODERATOR_ACCESS, Kernel::JSON_API])->group(function () {
    Route::post('users/{id}/confirm', [AuthController::class, 'confirm']);

    Route::post('users/{id}/switchToAgency', [AdminController::class, 'switchUserToAgency']);
    Route::post('users/{id}/switchToRegular', [AdminController::class, 'switchUserToRegular']);
    Route::post('users/{id}/grantAdvertising', [AdminController::class, 'grantAdvertising']);
    Route::post('users/{id}/denyAdvertising', [AdminController::class, 'denyAdvertising']);
    Route::post('users/{id}/grantPublishing', [AdminController::class, 'grantPublishing']);
    Route::post('users/{id}/denyPublishing', [AdminController::class, 'denyPublishing']);
});

Route::middleware([Kernel::AGENCY_ACCESS, Kernel::JSON_API])->group(function () {
    Route::get('users', [UsersController::class, 'browse']);
    Route::get('advertisers', [UsersController::class, 'advertisers']);
    Route::get('publishers', [UsersController::class, 'publishers']);

    Route::get('impersonation/{user}', [AuthController::class, 'impersonate']);
});
