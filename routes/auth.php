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

Route::middleware(Kernel::ANY)->group(
    function () {
        Route::post('login', 'App\AuthController@login');

        Route::get('users/email/confirm1Old/{token}', 'App\UsersController@emailChangeStep2');
        Route::get('users/email/confirm2New/{token}', 'App\UsersController@emailChangeStep3');
        Route::post('users/email/activate', 'App\UsersController@emailActivate');
    }
);

Route::middleware(Kernel::GUEST)->group(
    function () {
        Route::get('recovery/{token}', 'App\AuthController@recoveryTokenExtend');
        Route::post('recovery', 'App\AuthController@recovery');

        Route::post('users', 'App\UsersController@add')->name('app.users.add');
    }
);

Route::middleware(Kernel::API)->group(
    function () {
        Route::get('check', 'App\AuthController@check');
        Route::get('logout', 'App\AuthController@logout');

        Route::post('users/email/activate/resend', 'App\UsersController@emailActivateResend');
        Route::patch('users', 'App\UsersController@edit');
        Route::post('users/email', 'App\UsersController@emailChangeStep1');
    }
);
