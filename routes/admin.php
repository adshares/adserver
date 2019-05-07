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
use Adshares\Adserver\Http\Controllers\Manager\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('settings', [AdminController::class, 'listSettings']);
Route::put('settings', [AdminController::class, 'updateSettings']);

Route::get('license', [AdminController::class, 'getLicense']);

Route::get('terms', [AdminController::class, 'getTerms']);
Route::put('terms', [AdminController::class, 'putTerms']);
Route::get('privacy', [AdminController::class, 'getPrivacyPolicy']);
Route::put('privacy', [AdminController::class, 'putPrivacyPolicy']);

Route::get('users', [UsersController::class, 'browse'])
    ->name('app.users.browse');

//    Route::get('users/count', [UsersController::class, 'count'])->name('app.users.count');
//    Route::get('users/{user_id}', [UsersController::class, 'read'])->name('app.users.read');
//    Route::post('users', [UsersController::class, 'add'])->name('app.users.add');
//    Route::put('users/{user_id}', [UsersController::class, 'edit'])->name('app.users.edit');
//    Route::patch('users/{user_id}', [UsersController::class, 'update'])->name('app.users.update');
//    Route::delete('users/{user_id}', [UsersController::class, 'delete'])->name('app.users.delete');
