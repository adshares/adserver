<?php

Route::prefix('app')->group(function () {
    Route::get('users/email/confirm1Old/{token}', 'App\UsersController@emailChangeStep2');
    Route::get('users/email/confirm2New/{token}', 'App\UsersController@emailChangeStep3');
    Route::patch('users/{user_id?}', 'App\UsersController@edit')->name('app.users.edit');
    Route::post('users/email/activate', 'App\UsersController@emailActivate');

    Route::middleware('guest')->group(function () {
        Route::post('auth/login', 'App\AuthController@login');
        Route::post('auth/recovery', 'App\AuthController@recovery');
        Route::get('auth/recovery/{token}', 'App\AuthController@recoveryTokenExtend');
        Route::post('users', 'App\UsersController@add')->name('app.users.add');
    });

    Route::middleware('user')->group(function () {
        Route::get('auth/check', 'App\AuthController@check');
        Route::get('auth/logout', 'App\AuthController@logout');

        Route::get('sites/count', 'App\SitesController@count')->name('app.sites.count');
        Route::delete('sites/{site}', 'App\SitesController@delete')->name('app.sites.delete');
        Route::get('sites', 'App\SitesController@browse')->name('app.sites.browse');
        Route::get('sites/{site}', 'App\SitesController@read')->name('app.sites.read');
        Route::patch('sites/{site}', 'App\SitesController@edit')->name('app.sites.edit');
        Route::post('sites', 'App\SitesController@add')->name('app.sites.add');
        Route::get('sites/targeting', 'App\SitesController@targeting')->name('app.sites.targeting');

        Route::get('campaigns', 'App\CampaignsController@browse')->name('app.sites.browse');
        Route::get('campaigns/targeting', 'App\CampaignsController@targeting')->name('app.campaigns.targeting');
        Route::post('campaigns', 'App\SitesController@add')->name('app.campaigns.add');
        Route::get('campaigns/{campaign}', 'App\SitesController@read')->name('app.campaigns.read');

        Route::delete('users/{user_id}', 'App\UsersController@delete')->name('app.users.delete');
        Route::get('users', 'App\UsersController@browse')->name('app.users.browse');
        Route::get('users/{user_id?}', 'App\UsersController@read')->name('app.users.read');
        Route::post('users/email', 'App\UsersController@emailChangeStep1');
        Route::post('users/email/activate/resend', 'App\UsersController@emailActivateResend');
    });
});
