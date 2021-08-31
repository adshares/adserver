<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
$aduserUrl = env('ADUSER_BASE_URL', env('ADUSER_INTERNAL_LOCATION', env('ADUSER_EXTERNAL_LOCATION')));

return [
    'name' => env('APP_NAME', 'AdServer'),
    'version' => env('APP_VERSION', '#'),
    'env' => $appEnv,
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
    'ads_operator_server_url' => env('ADS_OPERATOR_SERVER_URL', 'https://ads-operator.adshares.net'),
    'aduser_base_url' => $aduserUrl,
    'aduser_serve_subdomain' => env('ADUSER_SERVE_SUBDOMAIN'),
    'aduser_info_url' => env('ADUSER_INFO_URL', $aduserUrl . '/panel.html?rated=1&url={domain}'),
    'adpay_endpoint' => env('ADPAY_ENDPOINT'),
    'adselect_endpoint' => env('ADSELECT_ENDPOINT'),
    'x_adselect_version' => env('X_ADSELECT_VERSION', 'php'),

    'banner_force_https' => (bool)env('BANNER_FORCE_HTTPS', true),
    'check_zone_domain' => (bool)env('CHECK_ZONE_DOMAIN', false),
    'allow_zone_in_iframe' => (bool)env('ALLOW_ZONE_IN_IFRAME', true),
    'network_data_cache_ttl' => (int)env('NETWORK_DATA_CACHE_TTL', 60),

    'campaign_min_budget' => (int)env('CAMPAIGN_MIN_BUDGET',5000000000),
    'campaign_min_cpm' => (int)env('CAMPAIGN_MIN_CPM',5000000000),
    'campaign_min_cpa' => (int)env('CAMPAIGN_MIN_CPA',1000000000),
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
    'btc_withdraw' => (bool)env('BTC_WITHDRAW', false),
    'btc_withdraw_min_amount' => (int)env('BTC_WITHDRAW_MIN_AMOUNT', 10000000000000),
    'btc_withdraw_max_amount' => (int)env('BTC_WITHDRAW_MAX_AMOUNT', 1000000000000000),
    'btc_withdraw_fee' => (float)env('BTC_WITHDRAW_FEE', 0.05),
    'now_payments_api_key' => env('NOW_PAYMENTS_API_KEY'),
    'now_payments_ipn_secret' => env('NOW_PAYMENTS_IPN_SECRET'),
    'now_payments_currency' => env('NOW_PAYMENTS_CURRENCY', 'USD'),
    'now_payments_min_amount' => (int)env('NOW_PAYMENTS_MIN_AMOUNT', 25),
    'now_payments_max_amount' => (int)env('NOW_PAYMENTS_MAX_AMOUNT', 1000),
    'now_payments_fee' => (float)env('NOW_PAYMENTS_FEE', 0.05),
    'now_payments_exchange' => (bool)env('NOW_PAYMENTS_EXCHANGE', false),
    'fiat_deposit_min_amount' => (int)env('FIAT_DEPOSIT_MIN_AMOUNT', 2000),
    'fiat_deposit_max_amount' => (int)env('FIAT_DEPOSIT_MAX_AMOUNT', 100000),
    'exchange_currencies' => explode(',', env('EXCHANGE_CURRENCIES')),
    'exchange_api_url' => env('EXCHANGE_API_URL'),
    'exchange_api_key' => env('EXCHANGE_API_KEY'),
    'exchange_api_secret' => env('EXCHANGE_API_SECRET'),
    'max_page_zones' => (int)env('MAX_PAGE_ZONES', 4),
    'crm_mail_address_on_user_registered' => env('CRM_MAIL_ADDRESS_ON_USER_REGISTERED'),
    'crm_mail_address_on_campaign_created' => env('CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED'),
    'crm_mail_address_on_site_added' => env('CRM_MAIL_ADDRESS_ON_SITE_ADDED'),
    'cdn_provider' => env('CDN_PROVIDER'),
    'skynet_api_url' => env('SKYNET_API_URL'),
    'skynet_api_key' => env('SKYNET_API_KEY'),
    'skynet_cdn_url' => env('SKYNET_CDN_URL'),
    'site_filtering_require' => env('SITE_FILTERING_REQUIRE'),
    'site_filtering_exclude' => env('SITE_FILTERING_EXCLUDE'),
    'campaign_targeting_require' => env('CAMPAIGN_TARGETING_REQUIRE'),
    'campaign_targeting_exclude' => env('CAMPAIGN_TARGETING_EXCLUDE'),
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
