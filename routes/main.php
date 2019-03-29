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

use Adshares\Adserver\Http\Controllers\InfoController;
use Adshares\Adserver\Http\Controllers\Manager\CampaignsController;
use Adshares\Adserver\Http\Controllers\Manager\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/',
    function () {
        return '';
    })->name('login');

# API INFO
Route::get('/info', [InfoController::class, 'info']);
Route::get('/info.json', [InfoController::class, 'info'])->name('app.infoEndpoint');
Route::get('/upload-preview/{type}/{name}', [CampaignsController::class, 'uploadPreview'])->name('app.campaigns.upload_preview');

Route::get('/policies/privacy.html', [InfoController::class, 'privacyPolicy']);
Route::get('/policies/terms.html', [InfoController::class, 'terms']);

Route::get('/stats/report/{date_start}/{date_end}', [StatsController::class, 'publisherReport']);
