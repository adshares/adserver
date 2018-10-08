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

use Adshares\Adserver\Http\Controllers\App\CampaignsController;
use Adshares\Adserver\Http\Controllers\App\ChartsController;
use Adshares\Adserver\Http\Controllers\App\NotificationsController;
use Adshares\Adserver\Http\Controllers\App\SitesController;

Route::get('config/adshares-address', [
    \Adshares\Adserver\Http\Controllers\App\ConfigController::class,
    'adsharesAddress',
]);
Route::get('notifications', [NotificationsController::class, 'read']);
Route::get('settings/notifications', [
    \Adshares\Adserver\Http\Controllers\App\SettingsController::class,
    'readNotifications',
]);

Route::post('campaigns', [CampaignsController::class, 'add'])
    ->name('app.campaigns.add');
Route::get('campaigns', [CampaignsController::class, 'browse'])
    ->name('app.campaigns.browse');
Route::get('campaigns/count', [CampaignsController::class, 'count'])
    ->name('app.campaigns.count');
Route::get('campaigns/{campaign_id}', [CampaignsController::class, 'read'])
    ->name('app.campaigns.read');
Route::patch('campaigns/{campaign_id}', [CampaignsController::class, 'edit'])
    ->name('app.campaigns.edit');
Route::delete('campaigns/{campaign_id}', [CampaignsController::class, 'delete'])
    ->name('app.campaigns.delete');

Route::post('sites', [SitesController::class, 'add'])->name('app.sites.add');
Route::get('sites', [SitesController::class, 'browse'])
    ->name('app.sites.browse');
Route::get('sites/count', [SitesController::class, 'count'])
    ->name('app.sites.count');
Route::get('sites/{site_id}', [SitesController::class, 'read'])
    ->name('app.sites.read');
Route::patch('sites/{site_id}', [SitesController::class, 'edit'])
    ->name('app.sites.edit');
Route::delete('sites/{site_id}', [SitesController::class, 'delete'])
    ->name('app.sites.delete');

//Route::post('users', [UsersController::class, 'add'])->name('app.users.add');
//Route::get('users', [UsersController::class, 'browse'])->name('app.users.browse');
//Route::get('users/count', [UsersController::class, 'count'])->name('app.users.count');
//Route::get('users/{user_id}', [UsersController::class, 'read'])->name('app.users.read');
//Route::patch('users/{user_id}', [UsersController::class, 'edit'])->name('app.users.edit');
//Route::delete('users/{user_id}', [UsersController::class, 'delete'])->name('app.users.delete');

// tmp mocked solutions
Route::post('chart', [ChartsController::class, 'chart']);
Route::get('options/campaigns/targeting', [
    CampaignsController::class,
    'targeting',
]);
Route::get('options/sites/targeting', [SitesController::class, 'targeting']);
Route::post('publisher_chart', [ChartsController::class, 'publisherChart']);
Route::get('config/banners', [SitesController::class, 'banners']);
