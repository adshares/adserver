<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Config\AppMode;
use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckForMaintenanceMode extends PreventRequestsDuringMaintenance
{
    protected $except = [
        '/',
        '/info',
        '/info.json',
    ];

    public function handle($request, Closure $next)
    {
        if (AppMode::MAINTENANCE === AppMode::getAppMode() && !$this->inExceptArray($request)) {
            throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE);
        }
        return $next($request);
    }
}
