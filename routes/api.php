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
Route::get('/view.js', 'DemandController@viewScript')->name('demand-view.js');

Route::get('/l/context/{log_id}', 'DemandController@logContext')->name('log-context');
Route::get('/l/keywords/{log_id}', 'DemandController@logKeywords')->name('log-keywords');

Route::get('/supply/find', 'SupplyController@find')->name('supply-find');
Route::get('/supply/find.js', 'SupplyController@findScript')->name('supply-find.js');
Route::get('/l/n/view/{id}', 'SupplyController@logNetworkView')->name('log-network-view');
Route::get('/l/n/click/{id}', 'SupplyController@logNetworkClick')->name('log-network-click');
Route::get('/l/n/keywords/{log_id}', 'SupplyController@logNetworkKeywords')->name('log-network-keywords');
