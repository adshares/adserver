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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\InvalidArgumentException;

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

    public function testBlacklistedExampleCom(): void
    {
        SitesRejectedDomain::storeDomains(['example.com']);

        self::assertFalse(SitesRejectedDomain::isDomainRejected('adshares.net'));
        self::assertFalse(SitesRejectedDomain::isDomainRejected('dot.com'));

        self::assertTrue(SitesRejectedDomain::isDomainRejected('example.com'));
        self::assertTrue(SitesRejectedDomain::isDomainRejected('one.example.com'));
        self::assertTrue(SitesRejectedDomain::isDomainRejected('www.one.example.com'));

        SitesRejectedDomain::storeDomains(['adshares.net']);

        self::assertTrue(SitesRejectedDomain::isDomainRejected('adshares.net'));
        self::assertTrue(SitesRejectedDomain::isDomainRejected('all.adshares.net'));
    }

    public function testBlacklistedSpecial(): void
    {
        self::assertTrue(SitesRejectedDomain::isDomainRejected(''));
        self::assertTrue(SitesRejectedDomain::isDomainRejected('localhost'));
        self::assertTrue(SitesRejectedDomain::isDomainRejected('127.0.0.1'));
        self::assertTrue(SitesRejectedDomain::isDomainRejected('fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'));
    }

    public function testBlacklistTwice(): void
    {
        SitesRejectedDomain::storeDomains(['example.com']);
        SitesRejectedDomain::storeDomains(['example.com']);

        self::assertCount(1, SitesRejectedDomain::all());
    }

    public function testDomainRejectedReasonIdWhileDomainIsNotRejected(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Domain example.com is not rejected');

        SitesRejectedDomain::domainRejectedReasonId('example.com');
    }

    public function testDomainRejectedReasonIdWhileDomainIsEmpty(): void
    {
        self::assertNull(SitesRejectedDomain::domainRejectedReasonId(''));
    }
}
