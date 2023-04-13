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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Mail\SiteAdsTxtInvalid;
use Adshares\Adserver\Mail\SiteAdsTxtValid;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;

class SiteAdsTxtCheckCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:supply:site-ads-txt:check';

    public function testLock(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        $lock = new Lock(new Key(self::COMMAND_SIGNATURE), new FlockStore(), null, false);
        $lock->acquire();

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::FAILURE);
    }

    public function testHandleNewSite(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        /** @var Site $siteNotConfirmed */
        $siteNotConfirmed = Site::factory()->create([
            'ads_txt_check_at' => null,
            'ads_txt_confirmed_at' => null,
            'status' => Site::STATUS_PENDING_APPROVAL,
            'user_id' => User::factory()->create(),
        ]);
        $expectedSiteIds = [$siteNotConfirmed->id];
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::once())
            ->method('checkSites')
            ->with(
                self::callback(
                    fn(Collection $collection) => $collection->map(fn(Site $site) => $site->id)
                        ->diff($expectedSiteIds)
                        ->isEmpty()
                )
            )
            ->willReturn([$siteNotConfirmed->id => true]);
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNotNull($siteNotConfirmed->refresh()->ads_txt_confirmed_at);
        self::assertNotNull($siteNotConfirmed->ads_txt_check_at);
        self::assertEquals(0, $siteNotConfirmed->ads_txt_fails);
        Mail::assertQueued(SiteAdsTxtValid::class);
    }

    public function testHandleNewSiteWhileSkipOptionIsSet(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        /** @var Site $siteNotConfirmed */
        $siteNotConfirmed = Site::factory()->create([
            'ads_txt_check_at' => null,
            'ads_txt_confirmed_at' => null,
            'status' => Site::STATUS_PENDING_APPROVAL,
            'user_id' => User::factory()->create(),
        ]);
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::never())->method('checkSites');
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE, ['--skip-unconfirmed' => true])->assertExitCode(Command::SUCCESS);

        self::assertNull($siteNotConfirmed->refresh()->ads_txt_confirmed_at);
        self::assertNull($siteNotConfirmed->ads_txt_check_at);
        self::assertEquals(0, $siteNotConfirmed->ads_txt_fails);
        Mail::assertNothingQueued();
    }

    public function testHandleWhileConfirmedSiteDoesNotHaveValidAdsTxt(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        /** @var Site $siteConfirmedYesterday */
        $siteConfirmedYesterday = Site::factory()->create([
            'ads_txt_confirmed_at' => new DateTimeImmutable('-25 hours'),
            'user_id' => User::factory()->create(),
        ]);
        $expectedSiteIds = [$siteConfirmedYesterday->id];
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::once())
            ->method('checkSites')
            ->with(
                self::callback(
                    fn(Collection $collection) => $collection->map(fn(Site $site) => $site->id)
                        ->diff($expectedSiteIds)
                        ->isEmpty()
                )
            )
            ->willReturn([$siteConfirmedYesterday->id => false]);
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNull($siteConfirmedYesterday->refresh()->ads_txt_confirmed_at);
        self::assertNotNull($siteConfirmedYesterday->ads_txt_check_at);
        self::assertEquals(1, $siteConfirmedYesterday->ads_txt_fails);
        self::assertEquals(Site::STATUS_PENDING_APPROVAL, $siteConfirmedYesterday->status);
        Mail::assertQueued(SiteAdsTxtInvalid::class);
    }

    public function testHandleConfirmedSiteWhileSkipOptionIsSet(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        /** @var Site $siteConfirmedYesterday */
        $siteConfirmedYesterday = Site::factory()->create([
            'ads_txt_confirmed_at' => new DateTimeImmutable('-25 hours'),
            'user_id' => User::factory()->create(),
        ]);
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::never())->method('checkSites');
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE, ['--skip-confirmed' => true])->assertExitCode(Command::SUCCESS);

        self::assertNotNull($siteConfirmedYesterday->refresh()->ads_txt_confirmed_at);
        self::assertEquals(0, $siteConfirmedYesterday->ads_txt_fails);
        self::assertEquals(Site::STATUS_ACTIVE, $siteConfirmedYesterday->status);
        Mail::assertNothingQueued();
    }

    public function testHandleOldSiteRejection(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        /** @var Site $site */
        $site = Site::factory()->create([
            'ads_txt_check_at' => new DateTimeImmutable('-6 days'),
            'ads_txt_confirmed_at' => null,
            'ads_txt_fails' => 13,
            'status' => Site::STATUS_PENDING_APPROVAL,
            'user_id' => User::factory()->create(),
        ]);
        $expectedSiteIds = [$site->id];
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::once())
            ->method('checkSites')
            ->with(
                self::callback(
                    fn(Collection $collection) => $collection->map(fn(Site $site) => $site->id)
                        ->diff($expectedSiteIds)
                        ->isEmpty()
                )
            )
            ->willReturn([$site->id => false]);
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNull($site->refresh()->ads_txt_confirmed_at);
        self::assertNotNull($site->ads_txt_check_at);
        self::assertEquals(14, $site->ads_txt_fails);
        self::assertEquals(Site::STATUS_REJECTED, $site->status);
        self::assertEquals('File ads.txt is missing', $site->reject_reason);
        Mail::assertNothingQueued();
    }

    public function testHandleSiteRecentlyConfirmed(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1']);
        Site::factory()->create([
            'ads_txt_confirmed_at' => new DateTimeImmutable(),
            'user_id' => User::factory()->create(),
        ]);
        $this->app->bind(AdsTxtCrawler::class, function () {
            $mock = $this->createMock(AdsTxtCrawler::class);
            $mock->expects(self::never())->method('checkSites');
            return $mock;
        });

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        Mail::assertNothingQueued();
    }

    public function testHandleWhileAdsTxtDisabled(): void
    {
        /** @var Site $siteNotConfirmed */
        $siteNotConfirmed = Site::factory()->create([
            'ads_txt_confirmed_at' => null,
            'ads_txt_check_at' => null,
            'user_id' => User::factory()->create(),
        ]);
        $this->app->bind(AdsTxtCrawler::class, function () {
            $mock = $this->createMock(AdsTxtCrawler::class);
            $mock->expects(self::never())->method('checkSites');
            return $mock;
        });

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNull($siteNotConfirmed->refresh()->ads_txt_confirmed_at);
        Mail::assertNothingQueued();
    }
}
