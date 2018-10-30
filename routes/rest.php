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

use Adshares\Adserver\Http\Controllers\Rest\CampaignsController;
use Adshares\Adserver\Http\Controllers\Rest\SitesController;
use Adshares\Adserver\Http\Controllers\Rest\UsersController;

Route::get('campaigns', [CampaignsController::class, 'browse'])->name('app.campaigns.browse');
Route::get('campaigns/count', [CampaignsController::class, 'count'])->name('app.campaigns.count');
Route::get('campaigns/{campaign_id}', [CampaignsController::class, 'read'])->name('app.campaigns.read');
Route::post('campaigns', [CampaignsController::class, 'add'])->name('app.campaigns.add');
Route::patch('campaigns/{campaign_id}', [CampaignsController::class, 'edit'])->name('app.campaigns.edit');
Route::delete('campaigns/{campaign_id}', [CampaignsController::class, 'delete'])->name('app.campaigns.delete');

Route::post('campaigns/banner', [CampaignsController::class, 'upload'])->name('app.campaigns.upload');

Route::post('campaigns/{campaign_id}/classify', [CampaignsController::class, 'classify'])->name('app.campaigns.classify');
Route::delete('campaigns/{campaign_id}/classify', [CampaignsController::class, 'disableClassify'])->name('app.campaigns.disable_classify');


Route::get('sites', [SitesController::class, 'browse'])->name('app.sites.browse');
Route::get('sites/count', [SitesController::class, 'count'])->name('app.sites.count');
Route::get('sites/{site_id}', [SitesController::class, 'read'])->name('app.sites.read');
Route::post('sites', [SitesController::class, 'add'])->name('app.sites.add');
Route::patch('sites/{site_id}', [SitesController::class, 'edit'])->name('app.sites.edit');
Route::delete('sites/{site_id}', [SitesController::class, 'delete'])->name('app.sites.delete');

# only for admin
Route::get('users', [UsersController::class, 'browse'])->name('app.users.browse');
Route::get('users/count', [UsersController::class, 'count'])->name('app.users.count');
Route::get('users/{user_id}', [UsersController::class, 'read'])->name('app.users.read');
Route::post('users', [UsersController::class, 'add'])->name('app.users.add');
Route::patch('users/{user_id}', [UsersController::class, 'edit'])->name('app.users.edit');
Route::delete('users/{user_id}', [UsersController::class, 'delete'])->name('app.users.delete');

