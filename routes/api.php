<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/adshares/inventory/list', 'ApiController@adsharesInventoryList');
Route::get('/adshares/report/{tx_id}/{pay_to}', 'ApiController@adsharesTransactionReport');

Route::get('/click/{id}', 'DemandController@click')->name('banner-click');
Route::get('/serve/{id}', 'DemandController@serve')->name('banner-serve');
Route::get('/view/{id}', 'DemandController@view')->name('banner-view');
