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

use Adshares\Adserver\Http\Middleware\KeyCaseModifier;
use Adshares\Adserver\Http\Middleware\RequireGuestAccess;
use Barryvdh\Cors\HandleCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;

class Kernel extends HttpKernel
{
    private const CACHE_HEADERS = 'cache.headers';
    private const BINDINGS = 'bindings';
    private const AUTH = 'auth';
    private const GUEST = 'guest';
    private const KEY_CASE_MODIFIER = 'key_case_modifier';
    private const CORS = 'cors';
    const WEB = 'web';
    const API_AUTH = 'only-authenticated-users';
    const API_GUEST = 'only-guest-users';
    const API_ANY = 'any-user';

    protected $middleware = [
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        HandleCors::class,
        Middleware\TrustProxies::class,
        Middleware\TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        self::WEB => [
            self::CACHE_HEADERS,
        ],
        self::API_ANY => [
            self::BINDINGS,
            self::KEY_CASE_MODIFIER,
        ],
        self::API_AUTH => [
            self::AUTH . ':api',
            self::BINDINGS,
            self::KEY_CASE_MODIFIER,
        ],
        self::API_GUEST => [
            self::GUEST . ':api',
            self::BINDINGS,
            self::KEY_CASE_MODIFIER,
        ],
    ];

    protected $routeMiddleware = [
        self::GUEST => RequireGuestAccess::class,
        self::AUTH => Authenticate::class,
        self::BINDINGS => SubstituteBindings::class,
        self::KEY_CASE_MODIFIER => KeyCaseModifier::class,
        self::CACHE_HEADERS => SetCacheHeaders::class,
    ];
}
