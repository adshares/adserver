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

namespace Adshares\Adserver\Http;

use Adshares\Adserver\Http\Middleware\RequireGuestAccess;
use Adshares\Adserver\Http\Middleware\SnakeCasing;
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
    const API = 'api-group';
    const GUEST = 'guest-group';
    const ANY = 'web-group';

    protected $middleware = [
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        Middleware\TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        Middleware\TrustProxies::class,
    ];

    protected $middlewareGroups = [
        self::ANY => [
            'cors',
        ],
        self::GUEST => [
            'cors',
            'guest:api',
            'bindings',
        ],
        self::API => [
            'cors',
            'auth:api',
            'bindings',
        ],
    ];

    protected $routeMiddleware = [
        'cors' => HandleCors::class,
        'guest' => RequireGuestAccess::class,
        'auth' => Authenticate::class,
        'bindings' => SubstituteBindings::class,

        'auth.basic' => AuthenticateWithBasicAuth::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'signed' => ValidateSignature::class,
        'snake_casing' => SnakeCasing::class,
        'session' => StartSession::class,
    ];
}
