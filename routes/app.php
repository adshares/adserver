<?php

use Illuminate\Support\Facades\Route;

Route::middleware('user')->group(function () {
    Route::get('config/adshares-address', 'App\ConfigController@adsharesAddress');

    Route::delete('sites/{site_id}', 'App\SitesController@delete')->name('app.sites.delete');
    Route::get('sites', 'App\SitesController@browse')->name('app.sites.browse');
    Route::get('sites/count', 'App\SitesController@count')->name('app.sites.count');
    Route::get('sites/{site_id}', 'App\SitesController@read')->name('app.sites.read');
    Route::patch('sites/{site_id}', 'App\SitesController@edit')->name('app.sites.edit');
    Route::post('sites', 'App\SitesController@add')->name('app.sites.add');

    Route::delete('campaigns/{campaign_id}', 'App\CampaignsController@delete')->name('app.campaigns.delete');
    Route::get('campaigns', 'App\CampaignsController@browse')->name('app.campaigns.browse');
    Route::get('campaigns/count', 'App\CampaignsController@count')->name('app.campaigns.count');
    Route::get('campaigns/{campaign_id}', 'App\CampaignsController@read')->name('app.campaigns.read');
    Route::patch('campaigns/{campaign_id}', 'App\CampaignsController@edit')->name('app.sites.edit');
    Route::post('campaigns', 'App\CampaignsController@add')->name('app.campaigns.add');

    Route::get('notifications', 'App\NotificationsController@read');

    Route::get('users', 'App\UsersController@browse')->name('app.users.browse');

    // ApiUsersService
    Route::patch('users/{user_id}', 'App\UsersController@edit')->name('app.users.edit');
    Route::post('users/email', 'App\UsersController@emailChangeStep1');

    Route::get('settings/notifications', 'App\SettingsController@readNotifications');

    // tmp mocked solutions
    Route::post('chart', 'App\ChartsController@chart');

    Route::get('options/campaigns/targeting', 'App\CampaignsController@targeting');
    Route::get('options/sites/targeting', 'App\SitesController@targeting');
    Route::post('publisher_chart', 'App\ChartsController@publisherChart');

    Route::get('config/banners', 'App\SitesController@banners');
})
;
