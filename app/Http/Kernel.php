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

namespace Adshares\Adserver\Http;

use Barryvdh\Cors\HandleCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     * These middleware are run during every request to your application.
     */
    protected $middleware = [
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        Middleware\TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        Middleware\TrustProxies::class,
    ];
    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            Middleware\EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            Middleware\VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            // 'throttle:60,1',
            HandleCors::class,
            'auth:api',
            'bindings',
        ],

        'app' => [
            HandleCors::class,
            Middleware\EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            // \Adshares\Adserver\Http\Middleware\VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],
    ];
    /**
     * The application's route middleware.
     * These middleware may be assigned to groups or used individually.
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'bindings' => SubstituteBindings::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'cors' => HandleCors::class,
        'guest' => Middleware\NotAuthenticatedSessionRequired::class,
        'signed' => ValidateSignature::class,
        'snake_casing' => Middleware\SnakeCasing::class,
        'throttle' => ThrottleRequests::class,
        'user' => Middleware\AuthenticatedSessionRequired::class,
    ];
}
