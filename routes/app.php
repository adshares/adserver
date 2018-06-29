<?php

Route::prefix('app')->group(function () {
    Route::get('users', 'App\UsersController@browse')->name('app.users.browse');
    Route::get('users/{user}', 'App\UsersController@read')->name('app.users.read');
    Route::post('users', 'App\UsersController@add')->name('app.users.add');
    Route::delete('users/{user}', 'App\UsersController@delete')->name('app.users.delete');
    Route::post('users/email/activate', 'App\UsersController@emailActivate');
    Route::post('auth/login', 'App\AuthController@login');
    Route::get('auth/check', 'App\AuthController@check');
});
