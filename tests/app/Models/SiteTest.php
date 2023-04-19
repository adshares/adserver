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
use Adshares\Adserver\Models\SiteRejectReason;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\InvalidArgumentException;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

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
            'accepted but no ads.txt' => [
                function () {
                    Config::updateAdminSettings([
                        Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1',
                        Config::SITE_APPROVAL_REQUIRED => '*',
                    ]);
                    DatabaseConfigReader::overwriteAdministrationConfig();
                    return Site::factory()->make([
                        'accepted_at' => new DateTimeImmutable(),
                        'ads_txt_confirmed_at' => null,
                        'user_id' => User::factory()->create(),
                    ]);
                },
                Site::STATUS_PENDING_APPROVAL,
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
                fn() => Site::factory()->make(['accepted_at' => null]),
                Site::STATUS_ACTIVE,
            ],
        ];
    }

    public function testApprovalProcedureWhileRejectedDomainHasReason(): void
    {
        /** @var SiteRejectReason $siteRejectReason */
        $siteRejectReason = SiteRejectReason::factory()->create();
        SitesRejectedDomain::factory()->create([
            'domain' => 'rejected.com',
            'reject_reason_id' => $siteRejectReason,
        ]);
        /** @var Site $site */
        $site = Site::factory()->make([
            'accepted_at' => null,
            'domain' => 'sub.rejected.com',
            'url' => 'https://sub.rejected.com',
        ]);

        $site->approvalProcedure();

        self::assertEquals(Site::STATUS_REJECTED, $site->status);
        self::assertEquals($siteRejectReason->id, $site->reject_reason_id);
    }

    public function testGetRejectReasonAttribute(): void
    {
        /** @var SiteRejectReason $siteRejectReason */
        $siteRejectReason = SiteRejectReason::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['reject_reason_id' => $siteRejectReason]);

        self::assertEquals($siteRejectReason->reject_reason, $site->reject_reason);
    }

    public function testFetchByPublicId(): void
    {
        /** @var Site $site */
        $site = Site::factory()->create();

        self::assertNotNull(Site::fetchByPublicId($site->uuid));
    }


    public function testFetchSitesWhichNeedAdsTxtConfirmation(): void
    {
        Site::factory()->create([
            'ads_txt_check_at' => new DateTimeImmutable('-6 days'),
            'ads_txt_confirmed_at' => null,
            'ads_txt_fails' => 13,
            'status' => Site::STATUS_PENDING_APPROVAL,
        ]);

        $sites = Site::fetchSitesWhichNeedAdsTxtConfirmation();

        self::assertCount(1, $sites);
    }

    public function testFetchSitesWhichNeedAdsTxtReEvaluation(): void
    {
        Site::factory()->create([
            'ads_txt_confirmed_at' => new DateTimeImmutable('-25 hours'),
            'user_id' => User::factory()->create(),
        ]);

        $sites = Site::fetchSitesWhichNeedAdsTxtReEvaluation();

        self::assertCount(1, $sites);
    }

    public function testRejectByDomainsWhileIp(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Rejecting sites by domain "127.0.0.1" without reason');

        Site::rejectByDomains(['127.0.0.1']);
    }
}
