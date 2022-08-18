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

namespace Adshares\Adserver\Http;

use Adshares\Adserver\Http\Middleware\CamelizeJsonResponse;
use Adshares\Adserver\Http\Middleware\Impersonation;
use Adshares\Adserver\Http\Middleware\RequireAdminAccess;
use Adshares\Adserver\Http\Middleware\RequireAdvertiserAccess;
use Adshares\Adserver\Http\Middleware\RequireAgencyAccess;
use Adshares\Adserver\Http\Middleware\RequireGuestAccess;
use Adshares\Adserver\Http\Middleware\RequireModeratorAccess;
use Adshares\Adserver\Http\Middleware\RequirePublisherAccess;
use Adshares\Adserver\Http\Middleware\SnakizeRequest;
use Adshares\Adserver\Http\Middleware\TrustProxies;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Fruitcake\Cors\HandleCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;

class Kernel extends HttpKernel
{
    private const AUTH = 'auth';

    public const USER_ACCESS = 'only-authenticated-users';
    public const ONLY_AUTHENTICATED_USERS_EXCEPT_IMPERSONATION = 'only-authenticated-users-except-impersonation';
    public const ADMIN_ACCESS = 'only-admin-users';
    public const ADMIN_JWT_ACCESS = 'jwt-admin-users';
    public const MODERATOR_ACCESS = 'only-moderator-users';
    public const AGENCY_ACCESS = 'only-agency-users';
    public const GUEST_ACCESS = 'only-guest-users';
    public const ADVERTISER_ACCESS = 'only-advertisers';
    public const PUBLISHER_ACCESS = 'only-publishers';
    public const JSON_API = 'api';

    public const JSON_API_NO_TRANSFORM = 'api-no-transform';

    protected $middleware = [
        CheckForMaintenanceMode::class,
        TrustProxies::class,
        HandleCors::class,
    ];

    protected $middlewareGroups = [
        self::USER_ACCESS => [
            self::AUTH . ':api',
            Impersonation::class,
        ],
        self::ADVERTISER_ACCESS => [
            self::AUTH . ':api',
            Impersonation::class,
            RequireAdvertiserAccess::class,
        ],
        self::PUBLISHER_ACCESS => [
            self::AUTH . ':api',
            Impersonation::class,
            RequirePublisherAccess::class,
        ],
        self::ONLY_AUTHENTICATED_USERS_EXCEPT_IMPERSONATION => [
            self::AUTH . ':api',
        ],
        self::GUEST_ACCESS => [
            RequireGuestAccess::class,
        ],
        self::ADMIN_ACCESS => [
            self::AUTH . ':api',
            RequireAdminAccess::class,
        ],
        self::ADMIN_JWT_ACCESS => [
            self::AUTH . ':jwt',
            RequireAdminAccess::class,
        ],
        self::MODERATOR_ACCESS => [
            self::AUTH . ':api',
            RequireModeratorAccess::class,
        ],
        self::AGENCY_ACCESS => [
            self::AUTH . ':api',
            RequireAgencyAccess::class,
        ],
        self::JSON_API => [
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            SnakizeRequest::class,
            SubstituteBindings::class,
            #post-handle
            SetCacheHeaders::class,
            CamelizeJsonResponse::class,
        ],
        self::JSON_API_NO_TRANSFORM => [
            ValidatePostSize::class,
            SubstituteBindings::class,
            #post-handle
            SetCacheHeaders::class,
        ],
    ];

    protected $routeMiddleware = [
        self::AUTH => Authenticate::class,
    ];

    public function bootstrap()
    {
        parent::bootstrap();

        DatabaseConfigReader::overwriteAdministrationConfig();
    }
}
