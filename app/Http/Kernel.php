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

namespace Adshares\Adserver\Http;

use Barryvdh\Cors\HandleCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;

class Kernel extends HttpKernel
{
    const MIDDLEWARE_GUEST = 'guest';
    const MIDDLEWARE_USER = 'user';
    const API = 'api';
    const APP = 'app';
    const WEB = 'web';

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
        self::WEB => [
            'cors',
        ],

        self::APP => [
            'cors',
            'session',
            'bindings',
        ],

        self::API => [
            'cors',
            'auth:api',
            'bindings',
        ],
    ];
    /**
     * The application's route middleware.
     * These middleware may be assigned to groups or used individually.
     */
    protected $routeMiddleware = [
        'cors' => HandleCors::class,
        'auth' => Authenticate::class,
        'bindings' => SubstituteBindings::class,
        'session' => StartSession::class,

        'auth.basic' => AuthenticateWithBasicAuth::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'signed' => ValidateSignature::class,
        'snake_casing' => Middleware\SnakeCasing::class,
        self::MIDDLEWARE_GUEST => Middleware\NotAuthenticatedSessionRequired::class,
        self::MIDDLEWARE_USER => Middleware\AuthenticatedSessionRequired::class,
    ];
}
