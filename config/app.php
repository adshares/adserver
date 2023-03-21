<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

return [
    'name' => env('APP_NAME', 'AdServer'),
    'version' => (string)env('APP_VERSION', '#'),
    'env' => env('APP_ENV', 'production'),
    'url' => env('APP_URL', 'http://localhost'),
    'debug' => env('APP_DEBUG', false),
    'refresh_testing_database' => env('APP_REFRESH_TESTING_DATABASE', false),
    'setup' => (int)env('APP_SETUP', 0),
    'adserver_id' => env('APP_ID', env('ADSERVER_ID', 'a-name-that-does-not-collide')),
    'adshares_command' => env('ADSHARES_COMMAND'),
    'adshares_workingdir' => env('ADSHARES_WORKINGDIR'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Application Service Providers...
         */
        Adshares\Adserver\Providers\CloudflareIpServiceProvider::class,
        Adshares\Adserver\Providers\AppServiceProvider::class,
        Adshares\Adserver\Providers\AuthServiceProvider::class,
        Adshares\Adserver\Providers\EventServiceProvider::class,
        Adshares\Adserver\Providers\RepositoryServiceProvider::class,
        Adshares\Adserver\Providers\RouteServiceProvider::class,
        Adshares\Adserver\Providers\Supply\InventoryImporterProvider::class,
        Adshares\Adserver\Providers\Common\ClientProvider::class,
        Adshares\Adserver\Providers\Common\OptionsProvider::class,
        Adshares\Adserver\Providers\Common\PassportServiceProvider::class,
        Adshares\Adserver\Providers\Supply\PaymentDetailsVerifyProvider::class,
        Adshares\Adserver\Providers\Supply\ClassifyProvider::class,
        Adshares\Adserver\Providers\MacrosProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => [
        'App' => Illuminate\Support\Facades\App::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        'Auth' => Illuminate\Support\Facades\Auth::class,
        'Blade' => Illuminate\Support\Facades\Blade::class,
        'Broadcast' => Illuminate\Support\Facades\Broadcast::class,
        'Bus' => Illuminate\Support\Facades\Bus::class,
        'Cache' => Illuminate\Support\Facades\Cache::class,
        'Config' => Illuminate\Support\Facades\Config::class,
        'Cookie' => Illuminate\Support\Facades\Cookie::class,
        'Crypt' => Illuminate\Support\Facades\Crypt::class,
        'DB' => Adshares\Adserver\Facades\DB::class,
        'Eloquent' => Illuminate\Database\Eloquent\Model::class,
        'Event' => Illuminate\Support\Facades\Event::class,
        'File' => Illuminate\Support\Facades\File::class,
        'Gate' => Illuminate\Support\Facades\Gate::class,
        'Hash' => Illuminate\Support\Facades\Hash::class,
        'Lang' => Illuminate\Support\Facades\Lang::class,
        'Log' => Illuminate\Support\Facades\Log::class,
        'Mail' => Illuminate\Support\Facades\Mail::class,
        'Notification' => Illuminate\Support\Facades\Notification::class,
        'Password' => Illuminate\Support\Facades\Password::class,
        'Queue' => Illuminate\Support\Facades\Queue::class,
        'Redirect' => Illuminate\Support\Facades\Redirect::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
        'Request' => Illuminate\Support\Facades\Request::class,
        'Response' => Illuminate\Support\Facades\Response::class,
        'Route' => Illuminate\Support\Facades\Route::class,
        'Schema' => Illuminate\Support\Facades\Schema::class,
        'Session' => Illuminate\Support\Facades\Session::class,
        'Storage' => Illuminate\Support\Facades\Storage::class,
        'URL' => Illuminate\Support\Facades\URL::class,
        'Validator' => Illuminate\Support\Facades\Validator::class,
        'View' => Illuminate\Support\Facades\View::class,
    ],

    'foreign_bsc_wallet' => env('FOREIGN_BSC_WALLET'),
    'min_ads_batch_withdrawal' => env('MIN_ADS_BATCH_WITHDRAWAL'),
    'reviewer_user_id' => env('REVIEWER_USER_ID'),
    'use_random_wallet_for_foreign' => env('USE_RANDOM_WALLET_FOR_FOREIGN'),
    'foreign_default_site_js' => env('FOREIGN_DEFAULT_SITE_JS'),
    'foreign_preferred_zones' => [
        ['name' => '234x60', 'width' => '234', 'height' => '60'],
        ['name' => '240x400', 'width' => '240', 'height' => '400'],
        ['name' => '250x250', 'width' => '250', 'height' => '250'],
        ['name' => '300x250', 'width' => '300', 'height' => '250'],
        ['name' => '300x600', 'width' => '300', 'height' => '600'],
        ['name' => '320x100', 'width' => '320', 'height' => '100'],
        ['name' => '512x512', 'width' => '512', 'height' => '512'],
        ['name' => '980x120', 'width' => '980', 'height' => '120'],
        ['name' => '3840x2160', 'width' => '3840', 'height' => '2160'],
    ]
];
