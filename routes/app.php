<?php

use Illuminate\Http\Request;

#
# PANEL
#

Route::prefix('app')->group(function () {
    Route::post('users', 'App\UserController@register');
    Route::get('users/email/activate/{token}', 'App\UserController@emailActivate');
    Route::post('auth/login', 'App\AuthController@login');
    Route::get('auth/check', 'App\AuthController@check');
});
