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

Route::get('config/adshares-address', [\Adshares\Adserver\Http\Controllers\App\ConfigController::class,'adsharesAddress']);
Route::get('notifications', [\Adshares\Adserver\Http\Controllers\App\NotificationsController::class,'read']);
Route::get('settings/notifications', [\Adshares\Adserver\Http\Controllers\App\SettingsController::class,'readNotifications']);

Route::post('campaigns', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'add'])->name('app.campaigns.add');
Route::get('campaigns', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'browse'])->name('app.campaigns.browse');
Route::get('campaigns/count', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'count'])->name('app.campaigns.count');
Route::get('campaigns/{campaign_id}', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'read'])->name('app.campaigns.read');
Route::patch('campaigns/{campaign_id}', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'edit'])->name('app.campaigns.edit');
Route::delete('campaigns/{campaign_id}', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'delete'])->name('app.campaigns.delete');

Route::post('sites', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'add'])->name('app.sites.add');
Route::get('sites', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'browse'])->name('app.sites.browse');
Route::get('sites/count', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'count'])->name('app.sites.count');
Route::get('sites/{site_id}', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'read'])->name('app.sites.read');
Route::patch('sites/{site_id}', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'edit'])->name('app.sites.edit');
Route::delete('sites/{site_id}', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'delete'])->name('app.sites.delete');

//Route::post('users', [\Adshares\Adserver\Http\Controllers\App\UsersController::class,'add'])->name('app.users.add');
//Route::get('users', [\Adshares\Adserver\Http\Controllers\App\UsersController::class,'browse'])->name('app.users.browse');
//Route::get('users/count', [\Adshares\Adserver\Http\Controllers\App\UsersController::class,'count'])->name('app.users.count');
//Route::get('users/{user_id}', [\Adshares\Adserver\Http\Controllers\App\UsersController::class,'read'])->name('app.users.read');
//Route::patch('users/{user_id}', [\Adshares\Adserver\Http\Controllers\App\UsersController::class,'edit'])->name('app.users.edit');
//Route::delete('users/{user_id}', [\Adshares\Adserver\Http\Controllers\App\UsersController::class,'delete'])->name('app.users.delete');

// tmp mocked solutions
Route::post('chart', [\Adshares\Adserver\Http\Controllers\App\ChartsController::class,'chart']);
Route::get('options/campaigns/targeting', [\Adshares\Adserver\Http\Controllers\App\CampaignsController::class,'targeting']);
Route::get('options/sites/targeting', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'targeting']);
Route::post('publisher_chart', [\Adshares\Adserver\Http\Controllers\App\ChartsController::class,'publisherChart']);
Route::get('config/banners', [\Adshares\Adserver\Http\Controllers\App\SitesController::class,'banners']);
