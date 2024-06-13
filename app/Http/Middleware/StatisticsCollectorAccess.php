<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Middleware;

use Adshares\Adserver\Utilities\AdsAuthenticator;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StatisticsCollectorAccess
{
    private const STATISTICS_COLLECTOR_ADS_ADDRESS = '0001-00000034-EDB8';

    public function __construct(private readonly AdsAuthenticator $adsAuthenticator)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return mixed
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $account = $this->adsAuthenticator->verifyRequest($request);
        if ($account !== self::STATISTICS_COLLECTOR_ADS_ADDRESS) {
            throw new AccessDeniedHttpException();
        }
        return $next($request);
    }
}
