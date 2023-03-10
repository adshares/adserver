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
use Adshares\Adserver\Services\Supply\OpenRtbBridge;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;

class OpenRtbBridgeTest extends TestCase
{
    public function testIsActiveWhileNotConfigured(): void
    {
        self::assertFalse(OpenRtbBridge::isActive());
    }

    public function testIsActiveWhileConfigured(): void
    {
        $this->initOpenRtb();

        self::assertTrue(OpenRtbBridge::isActive());
    }

    private function initOpenRtb(array $settings = []): void
    {
        $mergedSettings = array_merge(
            [
                Config::OPEN_RTB_BRIDGE_ACCOUNT_ADDRESS => '0001-00000004-DBEB',
                Config::OPEN_RTB_BRIDGE_URL => 'https://example.com',
            ],
            $settings,
        );
        Config::updateAdminSettings($mergedSettings);
        DatabaseConfigReader::overwriteAdministrationConfig();
    }
}
