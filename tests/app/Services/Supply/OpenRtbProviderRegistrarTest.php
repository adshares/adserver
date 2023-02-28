<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Supply\OpenRtbProviderRegistrar;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;

class OpenRtbProviderRegistrarTest extends TestCase
{
    public function testRegisterAsNetworkHost(): void
    {
        Config::updateAdminSettings([
            Config::OPEN_RTB_PROVIDER_ACCOUNT_ADDRESS => '0001-00000001-8B4E',
            Config::OPEN_RTB_PROVIDER_URL => 'https://example.com',
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $registrar = new OpenRtbProviderRegistrar();

        $registrar->registerAsNetworkHost();

        self::assertDatabaseHas(NetworkHost::class, [
            'address' => '0001-00000001-8B4E',
            'host' => 'https://example.com',
        ]);
    }
}
