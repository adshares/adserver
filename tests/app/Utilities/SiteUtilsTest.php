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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\SiteUtils;
use PHPUnit\Framework\TestCase;

final class SiteUtilsTest extends TestCase
{
    /**
     * @dataProvider cryptovoxelsDomainProvider
     */
    public function testExtractNameFromCryptovoxelsDomain(string $domain, string $expectedName): void
    {
        $name = SiteUtils::extractNameFromCryptovoxelsDomain($domain);

        $this->assertEquals($expectedName, $name);
    }

    public function cryptovoxelsDomainProvider(): array
    {
        return [
            ['scene-0.cryptovoxels.com', 'Cryptovoxels 0'],
            ['scene-1.cryptovoxels.com', 'Cryptovoxels 1'],
            ['scene-127.cryptovoxels.com', 'Cryptovoxels 127'],
        ];
    }

    /**
     * @dataProvider decentralandDomainProvider
     */
    public function testExtractNameFromDecentralandDomain(string $domain, string $expectedName): void
    {
        $name = SiteUtils::extractNameFromDecentralandDomain($domain);

        $this->assertEquals($expectedName, $name);
    }

    public function decentralandDomainProvider(): array
    {
        return [
            ['scene-0-n1.decentraland.org', 'Decentraland (0, -1)'],
            ['scene-n1-1.decentraland.org', 'Decentraland (-1, 1)'],
            ['scene-N55-N127.decentraland.org', 'Decentraland (-55, -127)'],
            ['scene-0-0.decentraland.org', 'DCL Builder'],
        ];
    }

    /**
     * @dataProvider polkaCityDomainProvider
     */
    public function testExtractNameFromPolkaCityDomain(string $domain, string $expectedName): void
    {
        $name = SiteUtils::extractNameFromPolkaCityDomain($domain);

        $this->assertEquals($expectedName, $name);
    }

    public function polkaCityDomainProvider(): array
    {
        return [
            ['plane.polkacity.io', 'PolkaCity (plane)'],
            ['billboard01.polkacity.io', 'PolkaCity (billboard01)'],
        ];
    }

    /**
     * @dataProvider isValidCryptovoxelsUrlProvider
     */
    public function testIsValidCryptovoxelsUrl(string $url, bool $isValid): void
    {
        $this->assertEquals($isValid, SiteUtils::isValidCryptovoxelsUrl($url));
    }

    public function isValidCryptovoxelsUrlProvider(): array
    {
        return [
            ['https://scene-0.cryptovoxels.com', true],
            ['https://scene-1.cryptovoxels.com', true],
            ['https://scene-127.cryptovoxels.com', true],
            ['https://scene-c858cff6-be79-41bb-8e13-3ce55cdbf5b0.cryptovoxels.com', false],
            ['http://scene-1.cryptovoxels.com', false],
            ['https://new.scene-127.cryptovoxels.com', false],
            ['https://play.cryptovoxels.com', false],
            ['https://example.com', false],
            ['https://scene-c858cff6-be79-41bb.cryptovoxels.com', false],
        ];
    }

    /**
     * @dataProvider isValidDecentralandUrlProvider
     */
    public function testIsValidDecentralandUrl(string $url, bool $isValid): void
    {
        $this->assertEquals($isValid, SiteUtils::isValidDecentralandUrl($url));
    }

    public function isValidDecentralandUrlProvider(): array
    {
        return [
            ['https://scene-0-n1.decentraland.org', true],
            ['https://scene-n1-1.decentraland.org', true],
            ['https://scene-N55-N127.decentraland.org', true],
            ['https://scene-0-0.decentraland.org', true],
            ['http://scene-0-0.decentraland.org', false],
            ['https://new.scene-0-0.decentraland.org', false],
            ['https://play.decentraland.org', false],
            ['https://example.com', false],
        ];
    }

    /**
     * @dataProvider isValidPolkaCityUrlProvider
     */
    public function testIsValidPolkaCityUrl(string $url, bool $isValid): void
    {
        $this->assertEquals($isValid, SiteUtils::isValidPolkaCityUrl($url));
    }

    public function isValidPolkaCityUrlProvider(): array
    {
        return [
            ['https://plane.polkacity.io', true],
            ['https://billboard01.polkacity.io', true],
            ['https://polkacity.io', false],
            ['https://example.com', false],
        ];
    }
}
