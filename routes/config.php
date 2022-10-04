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

declare(strict_types=1);

use Adshares\Adserver\Http\Controllers\Manager\ServerConfigurationController;
use Adshares\Adserver\Http\Controllers\Manager\ServerMonitoringController;
use Adshares\Adserver\Http\Kernel;
use Illuminate\Support\Facades\Route;

Route::middleware([Kernel::ADMIN_JWT_ACCESS, Kernel::JSON_API_NO_TRANSFORM])->group(function () {
    Route::get('config/placeholders/{key?}', [ServerConfigurationController::class, 'fetchPlaceholders']);
    Route::patch('config/placeholders', [ServerConfigurationController::class, 'storePlaceholders']);
    Route::get('config/{key?}', [ServerConfigurationController::class, 'fetch']);
    Route::patch('config', [ServerConfigurationController::class, 'store']);
    Route::put('config/{key}', [ServerConfigurationController::class, 'storeOne']);

    Route::get('monitoring/{key}', [ServerMonitoringController::class, 'fetch']);
    Route::patch('monitoring/hosts/{hostId}/reset', [ServerMonitoringController::class, 'resetHost']);
});
