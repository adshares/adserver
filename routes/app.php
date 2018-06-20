/<?php

use Illuminate\Http\Request;

#
# PANEL
#

Route::prefix('app')->group(function () {
    Route::get('users', 'App\UserController@browse')->name('app.users.browse');
    Route::get('users/{user}', 'App\UserController@read')->name('app.users.read');
    Route::post('users', 'App\UserController@add')->name('app.users.add');
    Route::delete('users/{user}', 'App\UserController@delete')->name('app.users.delete');
    Route::get('users/email/activate/{token}', 'App\UserController@emailActivate');
    Route::post('auth/login', 'App\AuthController@login');
    Route::get('auth/check', 'App\AuthController@check');
});
