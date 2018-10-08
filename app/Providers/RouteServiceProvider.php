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

namespace Adshares\Adserver\Providers;

use Adshares\Adserver\Http\Kernel;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    const PREFIX_AUTH = 'auth';
    const PREFIX_API = 'api';
    protected $namespace = 'Adshares\Adserver\Http\Controllers';

    public function map()
    {
        Route::namespace($this->namespace)
            ->group(base_path('routes/web.php'));

        Route::namespace($this->namespace)
            ->prefix(self::PREFIX_AUTH)
            ->group(base_path('routes/auth.php'));

        Route::namespace($this->namespace)
            ->prefix(self::PREFIX_API)
            ->middleware(Kernel::ONLY_AUTH)
            ->group(base_path('routes/rest.php'));
    }
}
