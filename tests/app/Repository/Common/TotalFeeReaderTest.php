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

namespace Adshares\Adserver\Tests\Repository\Common;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Repository\Common\TotalFeeReader;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Infrastructure\Service\CommunityFeeReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;

final class TotalFeeReaderTest extends TestCase
{
    public function testGetTotalFee(): void
    {
        Config::updateAdminSettings([
            Config::OPERATOR_RX_FEE => 0.5,
            Config::OPERATOR_TX_FEE => 0.5,
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $communityFeeReader = self::createMock(CommunityFeeReader::class);
        $communityFeeReader->method('getFee')->willReturn(0.5);
        $licenseReader = self::createMock(LicenseReader::class);
        $licenseReader->method('getFee')->willReturn(0.5);
        $reader = new TotalFeeReader($communityFeeReader, $licenseReader);

        $feeDemand = $reader->getTotalFeeDemand();
        $feeSupply = $reader->getTotalFeeSupply();

        self::assertEquals(0.875, $feeDemand);
        self::assertEquals(0.75, $feeSupply);
    }
}
