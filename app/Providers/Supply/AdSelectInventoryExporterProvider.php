<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Providers\Supply;

use Adshares\Adserver\Client\GuzzleAdSelectClient;
use Adshares\Supply\Application\Service\AdSelectInventoryExporter;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class AdSelectInventoryExporterProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(AdSelectInventoryExporter::class, function () {
            $client = new Client([
                'headers' => [ 'Content-Type' => 'application/json' ],
                'base_uri' => 'http://dev.e11.click:8091',
                'timeout'  => 5.0,
            ]);

            return new AdSelectInventoryExporter(new GuzzleAdSelectClient($client));
        });
    }
}
