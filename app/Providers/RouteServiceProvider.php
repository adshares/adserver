<?php
/**
 * Copyright (C) 2018 Adshares sp. z. o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Adshares\Adserver\Http\Controllers';

    public function map()
    {
        $this->mapAuthRoutes();

        $this->mapWebRoutes();
        $this->mapApiRoutes();
        $this->mapAppRoutes();

        $this->mapOldAuthRoutes();
    }

    private function mapWebRoutes()
    {
        Route::get('/', function () {
            return view('welcome');
        });
    }

    private function mapApiRoutes()
    {
        Route::middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'))
        ;
    }

    private function mapAppRoutes()
    {
        Route::middleware('app')
            ->namespace($this->namespace)
            ->prefix('app')
            ->group(base_path('routes/app.php'))
        ;

        Route::middleware('api')
            ->namespace($this->namespace)
            ->prefix('panel')
            ->group(base_path('routes/app.php'))
        ;
    }

    private function mapAuthRoutes(): void
    {
        Route::middleware(['app', 'user'])
            ->namespace($this->namespace)
            ->prefix('auth')
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

        Route::middleware(['app', 'guest'])
            ->namespace($this->namespace)
            ->prefix('auth')
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

        Route::middleware(['app'])
            ->namespace($this->namespace)
            ->prefix('auth')
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


    }

    /**
     * @deprecated This will be removed after AdPanel is upgraded to use token based auth
     */
    private function mapOldAuthRoutes(): void
    {
        Route::middleware(['app', 'guest'])
            ->namespace($this->namespace)
            ->prefix('app/auth')
            ->group(function () {
                Route::post('recovery', 'App\AuthController@recovery');
                Route::get('recovery/{token}', 'App\AuthController@recoveryTokenExtend');
                Route::post('login', 'App\AuthController@login');
            })
        ;

        Route::middleware(['app', 'user'])
            ->namespace($this->namespace)
            ->prefix('app/auth')
            ->group(function () {
                Route::get('check', 'App\AuthController@check');
                Route::get('logout', 'App\AuthController@logout');
            })
        ;
    }
}
