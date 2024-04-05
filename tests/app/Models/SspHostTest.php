<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\SspHost;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;

class SspHostTest extends TestCase
{
    public function testAdsPayment(): void
    {
        $sspHost = SspHost::create('0001-00000001-8B4E');

        self::assertFalse($sspHost->banned);

        $sspHost->ban();

        self::assertTrue($sspHost->banned);
    }

    public function testFetchAccepted(): void
    {
        SspHost::create('0001-00000001-8B4E', true);
        SspHost::create('0001-00000002-BB2D', true);

        $accepted = SspHost::fetchAccepted(config('app.inventory_export_whitelist'));

        self::assertCount(2, $accepted);
        $addresses = $accepted->map(fn($sspHost) => $sspHost->ads_address)->toArray();
        self::assertContains('0001-00000001-8B4E', $addresses);
        self::assertContains('0001-00000002-BB2D', $addresses);

        Config::updateAdminSettings([Config::INVENTORY_EXPORT_WHITELIST => '0001-00000001-8B4E']);
        DatabaseConfigReader::overwriteAdministrationConfig();

        $accepted = SspHost::fetchAccepted(config('app.inventory_export_whitelist'));

        self::assertCount(1, $accepted);
        $addresses = $accepted->map(fn($sspHost) => $sspHost->ads_address)->toArray();
        self::assertContains('0001-00000001-8B4E', $addresses);
    }
}
