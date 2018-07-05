<?php

Route::prefix('app')->group(function () {
    Route::post('users/email/activate', 'App\UsersController@emailActivate');

    Route::middleware('guest')->group(function () {
        Route::post('auth/login', 'App\AuthController@login');
        Route::post('users', 'App\UsersController@add')->name('app.users.add');
    });

    Route::middleware('user')->group(function () {
        Route::get('auth/check', 'App\AuthController@check');
        Route::get('auth/logout', 'App\AuthController@logout');

        Route::delete('sites/{site}', 'App\SitesController@delete')->name('app.sites.delete');
        Route::get('sites', 'App\SitesController@browse')->name('app.sites.browse');
        Route::get('sites/{site}', 'App\SitesController@read')->name('app.sites.read');
        Route::patch('sites/{site}', 'App\SitesController@edit')->name('app.sites.edit');
        Route::post('sites', 'App\SitesController@add')->name('app.sites.add');

        Route::delete('users/{user_id}', 'App\UsersController@delete')->name('app.users.delete');
        Route::get('users', 'App\UsersController@browse')->name('app.users.browse');
        Route::get('users/{user_id?}', 'App\UsersController@read')->name('app.users.read');
        Route::patch('users/{user_id?}', 'App\UsersController@edit')->name('app.users.edit');
        Route::post('users/email/activate/resend', 'App\UsersController@emailActivateResend');
    });
});
