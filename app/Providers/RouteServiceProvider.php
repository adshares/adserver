<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Http\Kernel;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Adshares\Adserver\Http\Controllers';

    const PREFIX_AUTH = 'auth';
    const PREFIX_APP = 'panel';
    const PREFIX_API = 'api';

    public function map()
    {
        Route::middleware(Kernel::WEB)
            ->get('/', function () { return view('welcome'); })
        ;

        $this->mapAuthRoutes();

        $this->mapApiRoutes();
        $this->mapAppRoutes();
    }

    private function mapAuthRoutes(): void
    {
        Route::middleware(Kernel::APP)
            ->namespace($this->namespace)
            ->prefix(self::PREFIX_AUTH)
            ->group(
                function () {
                    // ApiAuthService
                    Route::get('recovery/{token}', 'App\AuthController@recoveryTokenExtend');
                    Route::post('recovery', 'App\AuthController@recovery');

                    // ApiUsersService
                    Route::post('users', 'App\UsersController@add')->name('app.users.add');
                    Route::patch('users', 'App\UsersController@edit');
                }
            )
        ;

        Route::middleware(Kernel::WEB)
            ->namespace($this->namespace)
            ->prefix(self::PREFIX_AUTH)
            ->group(
                function () {
                    // ApiAuthService
                    Route::post('login', 'App\AuthController@login');

                    // ApiUsersService
                    Route::get('users/email/confirm1Old/{token}', 'App\UsersController@emailChangeStep2');
                    Route::get('users/email/confirm2New/{token}', 'App\UsersController@emailChangeStep3');
                    Route::post('users/email/activate', 'App\UsersController@emailActivate');
                }
            )
        ;

        Route::middleware(Kernel::API)
            ->namespace($this->namespace)
            ->prefix(self::PREFIX_AUTH)
            ->group(
                function () {
                    // ApiAuthService
                    Route::get('check', 'App\AuthController@check');
                    Route::get('logout', 'App\AuthController@logout');

                    // ApiUsersService
                    Route::post('users/email/activate/resend', 'App\UsersController@emailActivateResend');

                    Route::delete('users/{user_id}', 'App\UsersController@delete')->name('app.users.delete');
                    Route::get('users/{user_id?}', 'App\UsersController@read')->name('app.users.read');
                }
            )
        ;
    }

    private function mapApiRoutes()
    {
        Route::middleware(Kernel::API)
            ->namespace($this->namespace)
            ->prefix(self::PREFIX_API)
            ->group(base_path('routes/api.php'))
        ;
    }

    private function mapAppRoutes()
    {
        Route::middleware(Kernel::API)
            ->namespace($this->namespace)
            ->prefix(self::PREFIX_APP)
            ->group(base_path('routes/app.php'))
        ;
    }
}
