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

namespace Adshares\Adserver\Tests\Repository\Common;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Repository\Common\MySqlServerStatisticsRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;

final class MySqlServerStatisticsRepositoryTest extends TestCase
{
    public function testFetchInfoStatistics(): void
    {
        Config::updateAdminSettings([Config::JOINING_FEE_ENABLED => false]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $repository = new MySqlServerStatisticsRepository();

        $infoStatistics = $repository->fetchInfoStatistics();

        $infoStatisticsArray = $infoStatistics->toArray();
        self::assertEquals(0, $infoStatisticsArray['dsp']);
        self::assertNull($infoStatisticsArray['ssp']);
    }
}
