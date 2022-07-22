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

$appUrl = env('APP_URL', 'http://localhost');
$aduserUrl = env('ADUSER_BASE_URL', env('ADUSER_INTERNAL_LOCATION', env('ADUSER_EXTERNAL_LOCATION')));

return [
    'version' => env('APP_VERSION', '#'),
    'env' => env('APP_ENV', 'production'),
    'url' => $appUrl,
    'debug' => env('APP_DEBUG', false),
    'refresh_testing_database' => env('APP_REFRESH_TESTING_DATABASE', false),

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
    | Adshares Adserver & Network Components Integration configuration
    |--------------------------------------------------------------------------
    */

    'adpanel_url' => env('ADPANEL_URL'),
    'adserver_secret' => env('APP_KEY'),
    'adserver_id' => env('APP_ID', env('ADSERVER_ID', 'a-name-that-does-not-collide')),
    'adshares_command' => env('ADSHARES_COMMAND'),
    'adshares_workingdir' => env('ADSHARES_WORKINGDIR'),
    'ads_operator_server_url' => env('ADS_OPERATOR_SERVER_URL', 'https://ads-operator.adshares.net'),
    'ads_rpc_url' => env('ADS_RPC_URL', 'https://rpc.adshares.net'),
    'aduser_base_url' => $aduserUrl,
    'aduser_internal_url' => env('ADUSER_INTERNAL_URL', $aduserUrl),
    'aduser_serve_subdomain' => env('ADUSER_SERVE_SUBDOMAIN'),
    'aduser_info_url' => env('ADUSER_INFO_URL', $aduserUrl . '/panel.html?rated=1&url={domain}'),
    'adpay_endpoint' => env('ADPAY_ENDPOINT'),
    'adselect_endpoint' => env('ADSELECT_ENDPOINT'),

    'classifier_external_name' => env('CLASSIFIER_EXTERNAL_NAME'),
    'classifier_external_base_url' => env('CLASSIFIER_EXTERNAL_BASE_URL'),
    'classifier_external_public_key' => env('CLASSIFIER_EXTERNAL_PUBLIC_KEY'),
    'classifier_external_api_key_name' => env('CLASSIFIER_EXTERNAL_API_KEY_NAME'),
    'classifier_external_api_key_secret' => env('CLASSIFIER_EXTERNAL_API_KEY_SECRET'),
    'license_url' => env('ADSHARES_LICENSE_SERVER_URL', 'https://account.adshares.pl'),
    'license_key' => env('ADSHARES_LICENSE_KEY', env('ADSHARES_LICENSE_SERVER_KEY')),
    'license_id' => substr(env('ADSHARES_LICENSE_KEY', env('ADSHARES_LICENSE_SERVER_KEY')), 0, 10),
    'serve_base_url' => env('SERVE_BASE_URL') ?: $appUrl,
    'main_js_base_url' => env('MAIN_JS_BASE_URL') ?: $appUrl,
    'main_js_tld' => env('MAIN_JS_TLD'),
    'setup' => (int)env('APP_SETUP', 0),
    'skynet_api_url' => env('SKYNET_API_URL'),
    'skynet_api_key' => env('SKYNET_API_KEY'),
    'skynet_cdn_url' => env('SKYNET_CDN_URL'),
    'inventory_import_whitelist' =>
        array_filter(explode(',', env('INVENTORY_IMPORT_WHITELIST', env('INVENTORY_WHITELIST', '')))),
    'inventory_export_whitelist' =>
        array_filter(explode(',', env('INVENTORY_EXPORT_WHITELIST', env('INVENTORY_WHITELIST', '')))),
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
        Adshares\Adserver\Providers\RouteServiceProvider::class,
        Adshares\Adserver\Providers\Supply\InventoryImporterProvider::class,
        Adshares\Adserver\Providers\Common\ClientProvider::class,
        Adshares\Adserver\Providers\Common\OptionsProvider::class,
        Adshares\Adserver\Providers\Supply\PaymentDetailsVerifyProvider::class,
        Adshares\Adserver\Providers\Supply\ClassifyProvider::class,

        /*
         * JWT
         */
        Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
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
];
