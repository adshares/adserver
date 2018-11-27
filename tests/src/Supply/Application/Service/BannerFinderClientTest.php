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

declare(strict_types = 1);

namespace Adshares\Tests\Supply\Application\Service;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Application\Dto\ViewContext;
use Adshares\Supply\Application\Service\BannerFinderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BannerFinderClientTest extends TestCase
{
    use RefreshDatabase;

    public function testFindBanners(): void
    {
//        $this->app->bind(BannerFinderClient::class, function () {
//            $client = new Client([
//                'headers' => ['Content-Type' => 'application/json'],
//                'base_uri' => ,
//                'timeout' => 5.0,
//            ]);
//
//            return new JsonRpcAdSelectClient(new JsonRpc($client));
//        });

        $finder = $this->app->make(BannerFinderClient::class);
        $banners = $finder->findBanners(new ViewContext());

        self::assertCount(5, $banners);
    }
}
