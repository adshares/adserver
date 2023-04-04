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

namespace Adshares\Adserver\Tests\Http\Response\Site;

use Adshares\Adserver\Http\Response\LicenseResponse;
use Adshares\Adserver\Http\Response\Site\SizesResponse;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\License;
use DateTimeImmutable;

final class SizesResponseTest extends TestCase
{
    public function testToArrayNoSiteIdNoSite(): void
    {
        $response = new SizesResponse();

        $arr = $response->toArray();

        self::assertTrue(array_key_exists('sizes', $arr));
        self::assertIsArray($arr['sizes']);
        self::assertEmpty($arr['sizes']);
    }

    public function testToArraySiteIdNoSite(): void
    {
        $response = new SizesResponse(1);

        $arr = $response->toArray();

        self::assertTrue(array_key_exists('sizes', $arr));
        self::assertIsArray($arr['sizes']);
        self::assertEmpty($arr['sizes']);
    }

    public function testToArrayNoSiteIdSitesPresent(): void
    {
        foreach (['180x150', '468x60'] as $size) {
            Zone::factory()->create([
                'scopes' => [$size],
                'site_id' => Site::factory()->create(),
                'size' => $size,
            ]);
        }
        $response = new SizesResponse();

        $arr = $response->toArray();

        self::assertTrue(array_key_exists('sizes', $arr));
        self::assertIsArray($arr['sizes']);
        self::assertCount(2, $arr['sizes']);
        self::assertEqualsCanonicalizing(['180x150', '468x60'], $arr['sizes']);
    }

    public function testToArraySiteIdSitesPresent(): void
    {
        Zone::factory()->create([
            'scopes' => ['468x60'],
            'site_id' => Site::factory()->create(),
            'size' => '468x60',]);
        /** @var Site $site */
        $site = Site::factory()->create();
        /** @var Zone $zone */
        Zone::factory()->create([
            'scopes' => ['180x150'],
            'site_id' => $site,
            'size' => '180x150',]);
        $response = new SizesResponse($site->id);

        $arr = $response->toArray();

        self::assertTrue(array_key_exists('sizes', $arr));
        self::assertIsArray($arr['sizes']);
        self::assertCount(1, $arr['sizes']);
        self::assertEqualsCanonicalizing(['180x150'], $arr['sizes']);
    }
}
