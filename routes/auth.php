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

use Adshares\Adserver\Http\Controllers\AuthController;
use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('email/activate', [AuthController::class, 'emailActivate']);

Route::middleware(Kernel::USER_ACCESS)->group(function () {
    Route::post('email', [AuthController::class, 'emailChangeStep1']);
    Route::get('email/confirm1Old/{token}', [AuthController::class, 'emailChangeStep2']);
    Route::get('email/confirm2New/{token}', [AuthController::class, 'emailChangeStep3']);

    Route::get('check', [AuthController::class, 'check']);
    Route::get('logout', [AuthController::class, 'logout']);

    Route::patch('self', [AuthController::class, 'updateSelf']);
    Route::post('email/activate/resend', [AuthController::class, 'emailActivateResend']);
});

Route::middleware(Kernel::GUEST_ACCESS)->group(function () {
    Route::get('recovery/{token}', [AuthController::class, 'recoveryTokenExtend']);
    Route::post('recovery', [AuthController::class, 'recovery']);
    Route::post('register', [AuthController::class, 'register']);
    Route::patch('password', [AuthController::class, 'updateSelf']);
});

