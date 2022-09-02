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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Tests\TestCase;

class SitesRejectedDomainTest extends TestCase
{
    public function testStoreDomainsWhichWasDeleted(): void
    {
        $domainsToReject = ['xyz'];
        $rejectedDomain = 'abc.xyz';

        SitesRejectedDomain::storeDomains($domainsToReject);
        self::assertTrue(SitesRejectedDomain::isDomainRejected($rejectedDomain));

        SitesRejectedDomain::storeDomains([]);
        self::assertFalse(SitesRejectedDomain::isDomainRejected($rejectedDomain));

        SitesRejectedDomain::storeDomains($domainsToReject);
        self::assertTrue(SitesRejectedDomain::isDomainRejected($rejectedDomain));
    }
}
