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

use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;

Route::post('login', 'AuthController@login')->name('login');
Route::post('email/activate', 'AuthController@emailActivate');

Route::middleware(Kernel::ONLY_AUTH)->group(function () {
    Route::post('email', 'AuthController@emailChangeStep1');
    Route::get('email/confirm1Old/{token}', 'AuthController@emailChangeStep2');
    Route::get('email/confirm2New/{token}', 'AuthController@emailChangeStep3');

    Route::get('check', 'AuthController@check');
    Route::get('logout', 'AuthController@logout');

    Route::patch('self', 'AuthController@updateSelf');
    Route::post('email/activate/resend', 'AuthController@emailActivateResend');
});

Route::middleware(Kernel::ONLY_GUEST)->group(function () {
    Route::get('recovery/{token}', 'AuthController@recoveryTokenExtend');
    Route::post('recovery', 'AuthController@recovery');
    Route::post('register', 'AuthController@register');
    Route::patch('password', 'AuthController@updateSelf');
});

