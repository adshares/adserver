<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    private const PREFIX_AUTH = 'auth';

    private const PREFIX_ADMIN = 'admin';

    private const PREFIX_API = 'api';

    public function map(): void
    {
        Route::group([], base_path('routes/main.php'));
        Route::group([], base_path('routes/supply.php'));
        Route::group([], base_path('routes/demand.php'));

        Route::prefix(self::PREFIX_AUTH)
            ->group(base_path('routes/auth.php'));

        Route::prefix(self::PREFIX_ADMIN)
            ->group(base_path('routes/admin.php'));

        Route::prefix(self::PREFIX_API)
            ->group(base_path('routes/manager.php'));
    }
}
