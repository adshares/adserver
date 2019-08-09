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

use Adshares\Adserver\Http\Controllers\Manager\AuthController;
use Adshares\Adserver\Http\Controllers\Manager\SettingsController;
use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;

Route::middleware([Kernel::JSON_API])->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('email/activate', [AuthController::class, 'emailActivate']);
});

Route::middleware([Kernel::USER_ACCESS, Kernel::JSON_API])->group(function () {
    Route::post('email', [AuthController::class, 'emailChangeStep1']);
    Route::get('email/confirm1Old/{token}', [AuthController::class, 'emailChangeStep2']);
    Route::get('email/confirm2New/{token}', [AuthController::class, 'emailChangeStep3']);

    Route::get('check', [AuthController::class, 'check']);

    Route::patch('self', [AuthController::class, 'updateSelf']);
    Route::post('email/activate/resend', [AuthController::class, 'emailActivateResend']);
    Route::post('newsletter/subscription', [SettingsController::class, 'newsletterSubscription']);
});

Route::middleware([Kernel::ONLY_AUTHENTICATED_USERS_EXCEPT_IMPERSONATION, Kernel::JSON_API])->group(function () {
    Route::get('logout', [AuthController::class, 'logout']);
});

Route::middleware([Kernel::GUEST_ACCESS, Kernel::JSON_API])->group(function () {
    Route::get('recovery/{token}', [AuthController::class, 'recoveryTokenExtend']);
    Route::post('recovery', [AuthController::class, 'recovery']);

    Route::post('register', [AuthController::class, 'register']);
    Route::patch('password', [AuthController::class, 'updateSelf']);
});
