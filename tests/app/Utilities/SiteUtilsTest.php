<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\SiteUtils;
use PHPUnit\Framework\TestCase;

final class SiteUtilsTest extends TestCase
{
    /**
     * @dataProvider calculateAmountProvider
     */
    public function testExtractNameFromDecentralandDomain(string $domain, string $expectedName): void
    {
        $name = SiteUtils::extractNameFromDecentralandDomain($domain);

        $this->assertEquals($expectedName, $name);
    }

    public function calculateAmountProvider(): array
    {
        return [
            ['scene-0-n1.decentraland.org', 'Decentraland (0, -1)'],
            ['scene-n1-1.decentraland.org', 'Decentraland (-1, 1)'],
            ['scene-N55-N127.decentraland.org', 'Decentraland (-55, -127)'],
            ['scene-0-0.decentraland.org', 'DCL Builder'],
            ['new.scene-0-0.decentraland.org', 'new.scene-0-0.decentraland.org'],
            ['play.decentraland.org', 'play.decentraland.org'],
        ];
    }
}
