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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\InvalidArgumentException;
use Closure;
use DateTimeImmutable;

class SiteTest extends TestCase
{
    public function testFetchOrCreateWhileInvalidSiteId(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Site::factory()->create(['user_id' => $user]);

        self::expectException(InvalidArgumentException::class);

        Site::fetchOrCreate($user->id, 'https://example.com', 'metaverse', 'decentraland');
    }

    /**
     * @dataProvider fetchOrCreateWhileInvalidMetaverseUrlProvider
     */
    public function testFetchOrCreateWhileInvalidMetaverseUrl(string $vendor, string $url): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        self::expectException(InvalidArgumentException::class);

        Site::fetchOrCreate($user->id, $url, 'metaverse', $vendor);
    }

    public function fetchOrCreateWhileInvalidMetaverseUrlProvider(): array
    {
        return [
            'cryptovoxels' => ['cryptovoxels', 'https://example.com'],
            'decentraland' => ['decentraland', 'https://example.com'],
        ];
    }

    public function testFetchOrCreateFiltering(): void
    {
        Config::updateAdminSettings([
            Config::SITE_FILTERING_EXCLUDE_ON_AUTO_CREATE => '{"classify": ["0:0"]}',
            Config::SITE_FILTERING_REQUIRE_ON_AUTO_CREATE => '{"classify": ["0:1"]}',
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        /** @var User $user */
        $user = User::factory()->create();

        $site = Site::fetchOrCreate($user->id, 'https://example.com', 'web', null);
        self::assertContains('0:0', $site->site_excludes['classify']);
        self::assertContains('0:1', $site->site_requires['classify']);
    }

    public function testSetStatusAttribute(): void
    {
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user, 'status' => Site::STATUS_DRAFT]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site, 'status' => Zone::STATUS_DRAFT]);

        $site->status = Site::STATUS_ACTIVE;
        $site->push();

        self::assertEquals(Site::STATUS_ACTIVE, $site->refresh()->status);
        self::assertEquals(Zone::STATUS_ACTIVE, $zone->refresh()->status);
    }

    /**
     * @dataProvider approvalProcedureProvider
     */
    public function testApprovalProcedure(Closure $closure, int $expectedStatus): void
    {
        /** @var Site $site */
        $site = $closure();
        $site->approvalProcedure();

        self::assertEquals($expectedStatus, $site->status);
    }

    public function approvalProcedureProvider(): array
    {
        return [
            'already accepted' => [
                fn() => Site::factory()->make(['accepted_at' => new DateTimeImmutable()]),
                Site::STATUS_ACTIVE,
            ],
            'site rejected' => [
                function () {
                    $domain = 'rejected.com';
                    SitesRejectedDomain::factory()->create(['domain' => $domain]);
                    return Site::factory()->make([
                        'accepted_at' => null,
                        'domain' => $domain,
                        'url' => 'https://' . $domain,
                    ]);
                },
                Site::STATUS_REJECTED,
            ],
            'acceptance required' => [
                function () {
                    Config::updateAdminSettings([Config::SITE_APPROVAL_REQUIRED => '*']);
                    DatabaseConfigReader::overwriteAdministrationConfig();
                    $user = User::factory()->create();
                    return Site::factory()->make(['accepted_at' => null, 'user_id' => $user]);
                },
                Site::STATUS_PENDING_APPROVAL,
            ],
            'auto acceptance' => [
                fn () => Site::factory()->make(['accepted_at' => null]),
                Site::STATUS_ACTIVE,
            ],
        ];
    }
}
