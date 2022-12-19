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

use Adshares\Adserver\Http\Controllers\Manager\AuthController;
use Adshares\Adserver\Http\Controllers\Manager\OAuthController;
use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizedAccessTokenController;
use Laravel\Passport\Http\Controllers\PersonalAccessTokenController;
use Laravel\Passport\Http\Controllers\ScopeController;

Route::middleware([Kernel::JSON_API])->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::get('login/wallet/init', [AuthController::class, 'walletLoginInit']);
    Route::post('login/wallet', [AuthController::class, 'walletLogin']);
    Route::post('email/activate', [AuthController::class, 'emailActivate']);
    Route::post('foreign/register', [AuthController::class, 'foreignRegister']);
});

Route::middleware([Kernel::AUTH . ':api', Kernel::WEB])->group(function () {
    Route::get('/authorize', [OAuthController::class, 'authorizeUser']);
});

Route::middleware([Kernel::USER_ACCESS, Kernel::JSON_API])->group(function () {
    Route::post('email', [AuthController::class, 'emailChangeStep1']);
    Route::get('email/confirm1Old/{token}', [AuthController::class, 'emailChangeStep2']);
    Route::get('email/confirm2New/{token}', [AuthController::class, 'emailChangeStep3']);

    Route::get('check', [AuthController::class, 'check']);

    Route::patch('self', [AuthController::class, 'changePassword']);
    Route::post('password/confirm/{token}', [AuthController::class, 'confirmPasswordChange']);
    Route::post('email/activate/resend', [AuthController::class, 'emailActivateResend']);

    Route::get('/scopes', [ScopeController::class, 'all']);
    Route::get('/personal-access-tokens', [PersonalAccessTokenController::class, 'forUser']);
    Route::post('/personal-access-tokens', [PersonalAccessTokenController::class, 'store']);
    Route::delete('/personal-access-tokens/{token_id}', [PersonalAccessTokenController::class, 'destroy']);
});

Route::middleware([Kernel::ONLY_AUTHENTICATED_USERS_EXCEPT_IMPERSONATION, Kernel::JSON_API])->group(function () {
    Route::get('logout', [AuthController::class, 'logout']);
});

Route::middleware([Kernel::USER_JWT_ACCESS, Kernel::JSON_API])->group(function () {
    Route::delete('tokens/{token_id}', [AuthorizedAccessTokenController::class, 'destroy']);
});

Route::middleware([Kernel::GUEST_ACCESS, Kernel::JSON_API])->group(function () {
    Route::get('recovery/{token}', [AuthController::class, 'recoveryTokenExtend']);
    Route::post('recovery', [AuthController::class, 'recovery']);

    Route::post('register', [AuthController::class, 'register']);
    Route::patch('password', [AuthController::class, 'changePassword']);
});
