<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

$appEnv = env('APP_ENV', 'production');

return [
    'name' => env('APP_NAME', 'AdServer'),
    'version' => env('APP_VERSION', '#'),
    'env' => $appEnv,
    'url' => $appUrl,
    'debug' => env('APP_DEBUG', false),

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
    'adserver_id' => env('ADSERVER_ID', 'a-name-that-does-not-collide'),
    'adserver_banner_host' => env('ADSERVER_BANNER_HOST', $appUrl),
    'terms_url' => $appUrl.'/policies/terms.html',
    'privacy_url' => $appUrl.'/policies/privacy.html',
    'adshares_address' => env('ADSHARES_ADDRESS'),
    'adshares_wallet_cold_address' => env('ADSHARES_WALLET_COLD_ADDRESS'),
    'adshares_wallet_min_amount' => env('ADSHARES_WALLET_MIN_AMOUNT'),
    'adshares_wallet_max_amount' => env('ADSHARES_WALLET_MAX_AMOUNT'),
    'adshares_operator_email' => env('ADSHARES_OPERATOR_EMAIL'),
    'adshares_node_host' => env('ADSHARES_NODE_HOST'),
    'adshares_node_port' => env('ADSHARES_NODE_PORT'),
    'adshares_secret' => env('ADSHARES_SECRET'),
    'adshares_command' => env('ADSHARES_COMMAND'),
    'adshares_workingdir' => env('ADSHARES_WORKINGDIR'),
    'aduser_base_url' => env('ADUSER_BASE_URL', env('ADUSER_INTERNAL_LOCATION', env('ADUSER_EXTERNAL_LOCATION'))),
    'adpay_endpoint' => env('ADPAY_ENDPOINT'),
    'adselect_endpoint' => env('ADSELECT_ENDPOINT'),
    'banner_force_https' => (bool)env('BANNER_FORCE_HTTPS', true),
    'classify_public_key' => env('CLASSIFY_PUBLIC_KEY', ''),
    'classify_namespace' => (string)env('CLASSIFY_NAMESPACE', 'default_classify_namespace'),
    'classify_secret_key' => (string)env('CLASSIFY_SECRET_KEY', ''),
    'license_url' => env('ADSHARES_LICENSE_SERVER_URL'),
    'license_key' => env('ADSHARES_LICENSE_KEY', env('ADSHARES_LICENSE_SERVER_KEY')),
    'license_id' => substr(env('ADSHARES_LICENSE_SERVER_KEY'), 0, 10),

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
        Adshares\Adserver\Providers\Supply\AdSelectEventExporterProvider::class,
        Adshares\Adserver\Providers\Supply\PaymentDetailsVerifyProvider::class,
        Adshares\Adserver\Providers\Supply\ClassifyProvider::class,
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
