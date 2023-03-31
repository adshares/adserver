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

namespace Adshares\Tests\Supply\Application\Dto;

use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\Info;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    public function testSetDemandFeeFail(): void
    {
        $info = $this->getInfo(['capabilities' => [Info::CAPABILITY_PUBLISHER]]);

        self::expectException(RuntimeException::class);

        $info->setDemandFee(0.1);
    }

    public function testSetSupplyFeeFail(): void
    {
        $info = $this->getInfo(['capabilities' => [Info::CAPABILITY_ADVERTISER]]);

        self::expectException(RuntimeException::class);

        $info->setSupplyFee(0.1);
    }

    private function getInfo(array $merge = []): Info
    {
        return Info::fromArray(array_merge(
            [
                'capabilities' => [Info::CAPABILITY_PUBLISHER, Info::CAPABILITY_ADVERTISER],
                'inventoryUrl' => 'https://server.example.com/inventory',
                'module' => 'adserver',
                'name' => 'AdServer',
                'panelUrl' => 'https://panel.example.com',
                'privacyUrl' => 'https://panel.example.com/privacy-policy',
                'serverUrl' => 'https://server.example.com',
                'termsUrl' => 'https://panel.example.com/terms-of-service',
                'version' => '1.0.0',
            ],
            $merge,
        ));
    }
}
