<?php
/**
 * Copyright (c) 2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'slack'],
        ],

        'single' => [
            'driver' => 'single',
            'tap' => [Adshares\Adserver\Logging\CustomizeFormatter::class],
            'path' => env('LOG_FILE_PATH', storage_path('logs/adserver.log')),
            'level' => env('LOG_LEVEL', 'warning'),
        ],

        'daily' => [
            'driver' => 'daily',
            'tap' => [Adshares\Adserver\Logging\CustomizeFormatter::class],
            'path' => env('LOG_FILE_PATH', storage_path('logs/adserver.log')),
            'level' => env('LOG_LEVEL', 'warning'),
            'days' => 7,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('APP_NAME', 'AdServer'),
            'emoji' => env('LOG_SLACK_EMOJI', ':-1:'),
            'level' => env('LOG_SLACK_LEVEL', env('LOG_LEVEL', 'warning')),
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'warning'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'warning'),
        ],
    ],

];
