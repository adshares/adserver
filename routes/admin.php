<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
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
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Http\Controllers\Manager\AdminController;
use Adshares\Adserver\Http\Controllers\Manager\AuthController;
use Adshares\Adserver\Http\Controllers\Manager\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('settings', [AdminController::class, 'listSettings']);
Route::put('settings', [AdminController::class, 'updateSettings']);

Route::get('license', [AdminController::class, 'getLicense']);

Route::get('terms', [AdminController::class, 'getTerms']);
Route::put('terms', [AdminController::class, 'putTerms']);
Route::get('privacy', [AdminController::class, 'getPrivacyPolicy']);
Route::put('privacy', [AdminController::class, 'putPrivacyPolicy']);

Route::get('impersonate/{user}', [AuthController::class, 'impersonate']);
Route::get('impersonation/{user}', [AuthController::class, 'impersonate']);

Route::get('users', [UsersController::class, 'browse'])
    ->name('app.users.browse');
