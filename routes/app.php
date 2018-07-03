<?php

Route::prefix('app')->group(function () {
    // Auth
    Route::post('auth/login', 'App\AuthController@login');
    Route::get('auth/check', 'App\AuthController@check');
    // Users
    Route::get('users', 'App\UsersController@browse')->name('app.users.browse');
    Route::get('users/{user_id}', 'App\UsersController@read')->name('app.users.read');
    Route::post('users', 'App\UsersController@add')->name('app.users.add');
    Route::delete('users/{user_id}', 'App\UsersController@delete')->name('app.users.delete');
    Route::post('users/email/activate', 'App\UsersController@emailActivate');
    // Sites
    Route::get('sites', 'App\SitesController@browse')->name('app.sites.browse');
    Route::get('sites/{site}', 'App\SitesController@read')->name('app.sites.read');
    Route::post('sites', 'App\SitesController@add')->name('app.sites.add');
    Route::patch('sites/{site}', 'App\SitesController@edit')->name('app.sites.edit');
    Route::delete('sites/{site}', 'App\SitesController@delete')->name('app.sites.delete');
});
