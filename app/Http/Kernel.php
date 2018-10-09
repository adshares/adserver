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

use Adshares\Adserver\Http\Middleware\CamelizeResponse;
use Adshares\Adserver\Http\Middleware\RequireGuestAccess;
use Adshares\Adserver\Http\Middleware\SnakizeRequest;
use Adshares\Adserver\Http\Middleware\TrustProxies;
use Barryvdh\Cors\HandleCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;

class Kernel extends HttpKernel
{
    private const AUTH = 'auth';
    private const GUEST = 'guest';
    const USER_ACCESS = 'only-authenticated-users';
    const GUEST_ACCESS = 'only-guest-users';

    protected $middleware = [
        #pre
        CheckForMaintenanceMode::class,
        TrustProxies::class,
        HandleCors::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        SnakizeRequest::class,
//            SubstituteBindings::class,
        #post
        SetCacheHeaders::class,
        CamelizeResponse::class,
    ];

    protected $middlewareGroups = [
        self::USER_ACCESS => [
            self::AUTH . ':api',
        ],
        self::GUEST_ACCESS => [
            self::GUEST . ':api',
        ],
    ];

    protected $routeMiddleware = [
        self::GUEST => RequireGuestAccess::class,
        self::AUTH => Authenticate::class,
    ];
}
